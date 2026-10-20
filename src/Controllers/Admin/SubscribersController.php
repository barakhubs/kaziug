<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Http\Request;
use App\Core\Http\Responder;
use App\Core\Http\Response;
use App\Domain\DomainException;
use App\Domain\SubscriberStatus;
use App\Services\Contracts\SubscriberAdminServiceInterface;

final class SubscribersController
{
    use AdminContext;

    private const PER_PAGE = 25;
    private const MAX_UPLOAD_BYTES = 1024 * 1024;

    public function __construct(
        private readonly SubscriberAdminServiceInterface $subscribers,
        private readonly Responder $responder,
    ) {
    }

    public function index(Request $request): Response
    {
        $filters = [
            'q' => mb_substr(trim((string) $request->query('q', '')), 0, 100),
            'status' => $this->status($request->query('status')) ?? '',
        ];

        return $this->responder->view('admin/subscribers/index', [
            'result' => $this->subscribers->search($filters, max(1, (int) $request->query('page', 1)), self::PER_PAGE),
            'filters' => $filters,
            'counts' => $this->subscribers->countByStatus(),
        ]);
    }

    public function export(Request $request): Response
    {
        $status = $this->status($request->query('status'));
        $actor = $this->actor($request);
        $filename = 'subscribers-' . ($status ?? 'all') . '-' . date('Y-m-d') . '.csv';

        return new Response(function () use ($status, $actor): void {
            $out = fopen('php://output', 'w');
            $this->subscribers->export($status, $out, $actor);
            fclose($out);
        }, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Cache-Control' => 'no-store',
        ]);
    }

    public function showImport(Request $request): Response
    {
        return $this->responder->view('admin/subscribers/import', ['errors' => [], 'report' => null]);
    }

    public function import(Request $request): Response
    {
        $file = $request->file('csv');
        $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
        $path = (string) ($file['tmp_name'] ?? '');
        if ($error === UPLOAD_ERR_NO_FILE) {
            return $this->importForm(['csv' => 'Choose a CSV file.']);
        }
        if ($error !== UPLOAD_ERR_OK || (int) ($file['size'] ?? 0) > self::MAX_UPLOAD_BYTES || !is_file($path)) {
            return $this->importForm(['csv' => 'The file must be a CSV of 1 MB or less.']);
        }
        $content = (string) file_get_contents($path, false, null, 0, self::MAX_UPLOAD_BYTES);
        if (!mb_check_encoding($content, 'UTF-8')) {
            return $this->importForm(['csv' => 'Save the file as UTF-8 CSV and try again.']);
        }

        try {
            $report = $this->subscribers->import($content, $request->input('consent') === '1', $this->actor($request));
        } catch (DomainException $e) {
            return $this->importForm([$e->field ?? 'csv' => $e->getMessage()]);
        }

        return $this->responder->view('admin/subscribers/import', ['errors' => [], 'report' => $report]);
    }

    public function unsubscribe(Request $request): Response
    {
        $this->subscribers->unsubscribe($this->id($request), $this->actor($request));

        return $this->responder->redirectWith($request, '/admin/subscribers', 'success', 'Subscriber unsubscribed.');
    }

    public function delete(Request $request): Response
    {
        $this->subscribers->delete($this->id($request), $this->actor($request));

        return $this->responder->redirectWith($request, '/admin/subscribers', 'success', 'Subscriber and their delivery history deleted.');
    }

    /** @param array<string, string> $errors */
    private function importForm(array $errors): Response
    {
        return $this->responder->view('admin/subscribers/import', ['errors' => $errors, 'report' => null], 422);
    }

    private function status(mixed $value): ?string
    {
        return is_string($value) && in_array($value, SubscriberStatus::values(), true) ? $value : null;
    }
}
