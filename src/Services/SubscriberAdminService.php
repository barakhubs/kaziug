<?php

declare(strict_types=1);

namespace App\Services;

use App\Domain\Actor;
use App\Domain\DomainException;
use App\Domain\NotFoundException;
use App\Domain\Paginated;
use App\Domain\Pagination;
use App\Domain\Subscriber;
use App\Domain\TableQuery;
use App\Repositories\Contracts\SubscriberRepositoryInterface;
use App\Services\Contracts\AuditServiceInterface;
use App\Services\Contracts\SubscriberAdminServiceInterface;
use App\Services\Import\CsvSubscriberParser;

final class SubscriberAdminService implements SubscriberAdminServiceInterface
{
    public function __construct(
        private readonly SubscriberRepositoryInterface $subscribers,
        private readonly CsvSubscriberParser $parser,
        private readonly TokenService $tokens,
        private readonly AuditServiceInterface $audit,
    ) {
    }

    public function search(array $filters, TableQuery $table): Paginated
    {
        $result = $this->subscribers->search($filters, $table->perPage, $table->offset(), $table->sort, $table->direction);
        $pagination = new Pagination($table->page, $table->perPage, $result['total']);
        if ($pagination->page !== $table->page) {
            $result = $this->subscribers->search($filters, $table->perPage, $pagination->offset(), $table->sort, $table->direction);
        }

        return new Paginated($result['items'], $pagination);
    }

    public function countByStatus(): array
    {
        return $this->subscribers->countByStatus();
    }

    public function unsubscribe(int $id, Actor $actor): void
    {
        $subscriber = $this->get($id);
        $this->subscribers->unsubscribe($id);
        $this->audit->record($actor, 'subscriber.unsubscribed', 'subscriber', $id, ['email' => $subscriber->maskedEmail()]);
    }

    public function delete(int $id, Actor $actor): void
    {
        $subscriber = $this->get($id);
        $this->subscribers->delete($id);
        $this->audit->record($actor, 'subscriber.deleted', 'subscriber', $id, ['email' => $subscriber->maskedEmail()]);
    }

    public function export(?string $status, $handle, Actor $actor): int
    {
        CsvWriter::row($handle, ['email', 'name', 'status', 'consent_at', 'consent_ip', 'confirmed_at', 'created_at', 'unsubscribed_at']);
        $count = 0;
        foreach ($this->subscribers->each($status) as $s) {
            CsvWriter::row($handle, [
                $s->email, $s->name, $s->status->value, $s->consentAt?->format(DATE_ATOM), $s->consentIp,
                $s->confirmedAt?->format(DATE_ATOM), $s->createdAt->format(DATE_ATOM), $s->unsubscribedAt?->format(DATE_ATOM),
            ]);
            $count++;
        }
        $this->audit->record($actor, 'subscribers.exported', 'subscriber', null, ['status' => $status ?? 'all', 'rows' => $count]);

        return $count;
    }

    public function import(string $csv, bool $consentConfirmed, Actor $actor): array
    {
        if (!$consentConfirmed) {
            throw new DomainException('Confirm that everyone in the file agreed to receive job alerts.', 'consent');
        }

        $parsed = $this->parser->parse($csv);
        $emails = array_column($parsed['rows'], 'email');
        $existing = array_flip($this->subscribers->existingEmails($emails));

        $toInsert = [];
        foreach ($parsed['rows'] as $row) {
            if (!isset($existing[$row['email']])) {
                $toInsert[] = [...$row, 'unsubscribe_token' => $this->tokens->generate()];
            }
        }
        $imported = $toInsert === [] ? 0 : $this->subscribers->insertImported($toInsert);

        $report = [
            'imported' => $imported,
            'existing' => count($parsed['rows']) - $imported,
            'duplicates_in_file' => $parsed['duplicates_in_file'],
            'invalid' => $parsed['invalid'],
        ];
        $this->audit->record($actor, 'subscribers.imported', 'subscriber', null, [
            'imported' => $report['imported'],
            'existing' => $report['existing'],
            'duplicates_in_file' => $report['duplicates_in_file'],
            'invalid' => count($report['invalid']),
        ]);

        return $report;
    }

    private function get(int $id): Subscriber
    {
        return $this->subscribers->findById($id) ?? throw new NotFoundException('Subscriber not found.');
    }
}
