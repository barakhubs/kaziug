<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Http\Request;
use App\Core\Http\Responder;
use App\Core\Http\Response;
use App\Domain\DomainException;
use App\Domain\Newsletter;
use App\Domain\TableQuery;
use App\Repositories\Contracts\NewsletterRepositoryInterface;
use App\Mail\MailException;
use App\Services\Contracts\NewsletterServiceInterface;
use App\Services\Contracts\SmtpSettingsServiceInterface;
use App\Validation\NewsletterValidator;
use App\Validation\Rules\DateTimeRule;
use App\Validation\Validator;

final class NewslettersController
{
    use AdminContext;

    private const PER_PAGE = 20;

    public function __construct(
        private readonly NewsletterServiceInterface $newsletters,
        private readonly SmtpSettingsServiceInterface $smtp,
        private readonly NewsletterValidator $validator,
        private readonly Validator $plainValidator,
        private readonly Responder $responder,
    ) {
    }

    public function index(Request $request): Response
    {
        $q = mb_substr(trim((string) $request->query('q', '')), 0, 100);
        $table = TableQuery::fromQuery($request->queryAll(), NewsletterRepositoryInterface::SORTS, 'created', 'desc', self::PER_PAGE);
        $result = $this->newsletters->paginate($table, $q);
        $counts = [];
        foreach ($result->items as $newsletter) {
            $counts[$newsletter->id] = $this->newsletters->counts($newsletter->id);
        }

        return $this->responder->view('admin/newsletters/index', [
            'result' => $result,
            'counts' => $counts,
            'table' => $table->withPage($result->pagination->page),
            'q' => $q,
        ]);
    }

    public function create(Request $request): Response
    {
        return $this->form(null, [], []);
    }

    public function store(Request $request): Response
    {
        $input = $this->validator->validate($request->all());
        if ($input->fails()) {
            return $this->form(null, $request->all(), $input->errors, 422);
        }
        $newsletter = $this->newsletters->create($this->data($input->data), $this->actor($request));

        return $this->responder->redirectWith($request, "/admin/newsletters/{$newsletter->id}", 'success', 'Draft saved. Preview it and send a test before sending.');
    }

    public function show(Request $request): Response
    {
        $newsletter = $this->newsletters->get($this->id($request));

        return $this->responder->view('admin/newsletters/show', [
            'newsletter' => $newsletter,
            'counts' => $this->newsletters->counts($newsletter->id),
            'failures' => $this->newsletters->permanentFailures($newsletter->id),
            'mailConfigured' => $this->smtp->settings()->isConfigured(),
            'testTo' => $this->admin($request)->email,
            'errors' => [],
        ]);
    }

    public function edit(Request $request): Response
    {
        $newsletter = $this->newsletters->get($this->id($request));
        if (!$newsletter->status->isEditable()) {
            return $this->responder->redirectWith($request, "/admin/newsletters/{$newsletter->id}", 'error', 'Sent newsletters can’t be edited.');
        }

        return $this->form($newsletter, [
            'subject' => $newsletter->subject,
            'preheader' => $newsletter->preheader,
            'body_html' => $newsletter->bodyHtml,
            'body_text' => $newsletter->bodyText,
        ], []);
    }

    public function update(Request $request): Response
    {
        $newsletter = $this->newsletters->get($this->id($request));
        $input = $this->validator->validate($request->all());
        if ($input->fails()) {
            return $this->form($newsletter, $request->all(), $input->errors, 422);
        }

        try {
            $this->newsletters->update($newsletter->id, $this->data($input->data), $this->actor($request));
        } catch (DomainException $e) {
            return $this->responder->redirectWith($request, "/admin/newsletters/{$newsletter->id}", 'error', $e->getMessage());
        }

        return $this->responder->redirectWith($request, "/admin/newsletters/{$newsletter->id}", 'success', 'Newsletter saved.');
    }

    /** The rendered email, shown in a sandboxed iframe on the newsletter page. */
    public function preview(Request $request): Response
    {
        return Response::html($this->newsletters->previewHtml($this->id($request)))
            ->setHeader('Content-Security-Policy', "default-src 'none'; img-src https: data:; style-src 'unsafe-inline'; frame-ancestors 'self'; base-uri 'none'; form-action 'none'")
            ->setHeader('X-Frame-Options', 'SAMEORIGIN');
    }

    public function test(Request $request): Response
    {
        $id = $this->id($request);
        $input = $this->plainValidator->validate($request->all(), ['test_to' => 'required|email']);
        if ($input->fails()) {
            return $this->responder->redirectWith($request, "/admin/newsletters/{$id}", 'error', 'Enter a valid address for the test email.');
        }

        try {
            $this->newsletters->sendTest($id, (string) $input->data['test_to'], $this->actor($request));
        } catch (MailException $e) {
            return $this->responder->redirectWith($request, "/admin/newsletters/{$id}", 'error', 'Test email failed: ' . $e->getMessage());
        }

        return $this->responder->redirectWith($request, "/admin/newsletters/{$id}", 'success', "Test email sent to {$input->data['test_to']}.");
    }

    public function send(Request $request): Response
    {
        $id = $this->id($request);
        try {
            $queued = $this->newsletters->sendNow($id, $this->actor($request));
        } catch (DomainException $e) {
            return $this->responder->redirectWith($request, "/admin/newsletters/{$id}", 'error', $e->getMessage());
        }

        return $this->responder->redirectWith($request, "/admin/newsletters/{$id}", 'success',
            "Queued for {$queued} active subscriber(s). The background worker sends them, about a minute at a time.");
    }

    public function schedule(Request $request): Response
    {
        $id = $this->id($request);
        $at = DateTimeRule::parse(is_string($request->input('scheduled_at')) ? $request->input('scheduled_at') : null);
        if ($at === null) {
            return $this->responder->redirectWith($request, "/admin/newsletters/{$id}", 'error', 'Choose a date and time to send.');
        }

        try {
            $this->newsletters->schedule($id, $at, $this->actor($request));
        } catch (DomainException $e) {
            return $this->responder->redirectWith($request, "/admin/newsletters/{$id}", 'error', $e->getMessage());
        }

        return $this->responder->redirectWith($request, "/admin/newsletters/{$id}", 'success', 'Scheduled for ' . $at->format('j M Y, H:i') . '.');
    }

    public function unschedule(Request $request): Response
    {
        $id = $this->id($request);
        try {
            $this->newsletters->unschedule($id, $this->actor($request));
        } catch (DomainException $e) {
            return $this->responder->redirectWith($request, "/admin/newsletters/{$id}", 'error', $e->getMessage());
        }

        return $this->responder->redirectWith($request, "/admin/newsletters/{$id}", 'success', 'Unscheduled. The newsletter is a draft again.');
    }

    public function delete(Request $request): Response
    {
        $id = $this->id($request);
        try {
            $this->newsletters->delete($id, $this->actor($request));
        } catch (DomainException $e) {
            return $this->responder->redirectWith($request, "/admin/newsletters/{$id}", 'error', $e->getMessage());
        }

        return $this->responder->redirectWith($request, '/admin/newsletters', 'success', 'Draft deleted.');
    }

    /**
     * @param array<string, mixed> $data
     * @return array{subject: string, preheader: string, body_html: string, body_text: ?string}
     */
    private function data(array $data): array
    {
        return [
            'subject' => (string) $data['subject'],
            'preheader' => (string) ($data['preheader'] ?? ''),
            'body_html' => (string) $data['body_html'],
            'body_text' => $data['body_text'] === null ? null : (string) $data['body_text'],
        ];
    }

    /**
     * @param array<string, mixed> $values
     * @param array<string, string> $errors
     */
    private function form(?Newsletter $newsletter, array $values, array $errors, int $status = 200): Response
    {
        return $this->responder->view('admin/newsletters/form', ['newsletter' => $newsletter, 'values' => $values, 'errors' => $errors], $status);
    }
}
