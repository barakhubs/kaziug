<?php

declare(strict_types=1);

namespace App\Services;

use App\Domain\Actor;
use App\Domain\DeliveryCounts;
use App\Domain\DomainException;
use App\Domain\Newsletter;
use App\Domain\NewsletterStatus;
use App\Domain\NotFoundException;
use App\Domain\Paginated;
use App\Domain\Pagination;
use App\Domain\TableQuery;
use App\Mail\MailerInterface;
use App\Mail\NewsletterEmailBuilder;
use App\Repositories\Contracts\DeliveryRepositoryInterface;
use App\Repositories\Contracts\NewsletterRepositoryInterface;
use App\Repositories\Contracts\TransactionManagerInterface;
use App\Services\Contracts\AuditServiceInterface;
use App\Services\Contracts\NewsletterServiceInterface;
use App\Services\Contracts\SettingsReaderInterface;
use App\Services\Contracts\SmtpSettingsServiceInterface;
use DateTimeImmutable;

final class NewsletterService implements NewsletterServiceInterface
{
    public function __construct(
        private readonly NewsletterRepositoryInterface $newsletters,
        private readonly DeliveryRepositoryInterface $deliveries,
        private readonly TransactionManagerInterface $tx,
        private readonly HtmlSanitizer $sanitizer,
        private readonly HtmlToText $toText,
        private readonly NewsletterEmailBuilder $builder,
        private readonly MailerInterface $mailer,
        private readonly SmtpSettingsServiceInterface $smtp,
        private readonly SettingsReaderInterface $settings,
        private readonly AuditServiceInterface $audit,
    ) {
    }

    public function paginate(TableQuery $table, string $q = ''): Paginated
    {
        $result = $this->newsletters->paginate($table->perPage, $table->offset(), $q, $table->sort, $table->direction);
        $pagination = new Pagination($table->page, $table->perPage, $result['total']);
        if ($pagination->page !== $table->page) {
            $result = $this->newsletters->paginate($table->perPage, $pagination->offset(), $q, $table->sort, $table->direction);
        }

        return new Paginated($result['items'], $pagination);
    }

    public function get(int $id): Newsletter
    {
        return $this->newsletters->findById($id) ?? throw new NotFoundException('Newsletter not found.');
    }

    public function latest(): ?Newsletter
    {
        return $this->newsletters->latest();
    }

    public function create(array $data, Actor $actor): Newsletter
    {
        $id = $this->newsletters->create($this->prepare($data), $actor->adminId);
        $this->audit->record($actor, 'newsletter.created', 'newsletter', $id, ['subject' => $data['subject']]);

        return $this->get($id);
    }

    public function update(int $id, array $data, Actor $actor): void
    {
        $this->assertEditable($this->get($id));
        $this->newsletters->update($id, $this->prepare($data));
        $this->audit->record($actor, 'newsletter.updated', 'newsletter', $id, ['subject' => $data['subject']]);
    }

    public function delete(int $id, Actor $actor): void
    {
        $newsletter = $this->get($id);
        if ($newsletter->status !== NewsletterStatus::Draft) {
            throw new DomainException('Only drafts can be deleted. Unschedule it first.');
        }
        $this->newsletters->delete($id);
        $this->audit->record($actor, 'newsletter.deleted', 'newsletter', $id, ['subject' => $newsletter->subject]);
    }

    public function previewHtml(int $id): string
    {
        return $this->builder->buildSample($this->get($id), 'preview@example.com')->html;
    }

    public function sendTest(int $id, string $to, Actor $actor): void
    {
        $this->mailer->send($this->builder->buildSample($this->get($id), $to, '[Test] '));
        $this->audit->record($actor, 'newsletter.test_sent', 'newsletter', $id, ['to' => $to]);
    }

    public function sendNow(int $id, Actor $actor): int
    {
        $this->assertReadyToSend($this->get($id));

        $queued = $this->tx->transactional(function () use ($id): int {
            if (!$this->newsletters->transition($id, [NewsletterStatus::Draft, NewsletterStatus::Scheduled], NewsletterStatus::Sending)) {
                throw new DomainException('This newsletter is already being sent or was sent.');
            }

            return $this->deliveries->queueForActiveSubscribers($id);
        });
        $this->audit->record($actor, 'newsletter.queued', 'newsletter', $id, ['deliveries' => $queued]);

        return $queued;
    }

    public function schedule(int $id, DateTimeImmutable $at, Actor $actor): void
    {
        $newsletter = $this->get($id);
        $this->assertReadyToSend($newsletter);
        if ($at <= new DateTimeImmutable('+1 minute')) {
            throw new DomainException('Choose a time in the future, or use “Send now”.', 'scheduled_at');
        }
        if (!$this->newsletters->transition($id, [NewsletterStatus::Draft, NewsletterStatus::Scheduled], NewsletterStatus::Scheduled, $at)) {
            throw new DomainException('Only drafts and scheduled newsletters can be scheduled.');
        }
        $this->audit->record($actor, 'newsletter.scheduled', 'newsletter', $id, ['at' => $at->format(DATE_ATOM)]);
    }

    public function unschedule(int $id, Actor $actor): void
    {
        if (!$this->newsletters->transition($id, [NewsletterStatus::Scheduled], NewsletterStatus::Draft)) {
            throw new DomainException('This newsletter is not scheduled.');
        }
        $this->audit->record($actor, 'newsletter.unscheduled', 'newsletter', $id);
    }

    public function counts(int $id): DeliveryCounts
    {
        return $this->deliveries->counts($id);
    }

    public function permanentFailures(int $id): array
    {
        return $this->deliveries->permanentFailures($id);
    }

    /**
     * @param array{subject: string, preheader: string, body_html: string, body_text: ?string} $data
     * @return array{subject: string, preheader: string, body_html: string, body_text: string}
     */
    private function prepare(array $data): array
    {
        $html = $this->sanitizer->sanitize($data['body_html']);
        $text = trim((string) ($data['body_text'] ?? ''));

        return [
            'subject' => $data['subject'],
            'preheader' => $data['preheader'],
            'body_html' => $html,
            'body_text' => $text !== '' ? $text : $this->toText->convert($html),
        ];
    }

    private function assertEditable(Newsletter $newsletter): void
    {
        if (!$newsletter->status->isEditable()) {
            throw new DomainException('This newsletter has already been sent and can no longer be edited.');
        }
    }

    /** Every newsletter must carry a physical address, and mail must be configured. */
    private function assertReadyToSend(Newsletter $newsletter): void
    {
        $this->assertEditable($newsletter);
        if (trim(strip_tags($newsletter->bodyHtml)) === '') {
            throw new DomainException('The newsletter body is empty.');
        }
        if (trim($this->settings->site()->physicalAddress) === '') {
            throw new DomainException('Add a physical address in Site settings first: every newsletter must include it.');
        }
        if (!$this->smtp->settings()->isConfigured()) {
            throw new DomainException('Configure SMTP settings before sending.');
        }
    }
}
