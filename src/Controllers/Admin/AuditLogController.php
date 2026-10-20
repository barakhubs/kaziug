<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Http\Request;
use App\Core\Http\Responder;
use App\Core\Http\Response;
use App\Domain\Pagination;
use App\Services\Contracts\AuditServiceInterface;

final class AuditLogController
{
    private const PER_PAGE = 50;

    public function __construct(
        private readonly AuditServiceInterface $audit,
        private readonly Responder $responder,
    ) {
    }

    public function index(Request $request): Response
    {
        $options = $this->audit->filterOptions();
        $pick = static fn (mixed $value, array $allowed): string => is_string($value) && in_array($value, $allowed, true) ? $value : '';
        $date = static fn (mixed $value): string => is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1 && checkdate(
            (int) substr($value, 5, 2), (int) substr($value, 8, 2), (int) substr($value, 0, 4),
        ) ? $value : '';

        $filters = [
            'action' => $pick($request->query('action'), $options['actions']),
            'entity' => $pick($request->query('entity'), $options['entities']),
            'actor' => $pick($request->query('actor'), $options['actors']),
            'from' => $date($request->query('from')),
            'to' => $date($request->query('to')),
        ];
        $page = max(1, (int) $request->query('page', 1));
        $result = $this->audit->search($filters, $page, self::PER_PAGE);

        return $this->responder->view('admin/audit/index', [
            'rows' => $result['rows'],
            'pagination' => new Pagination($page, self::PER_PAGE, $result['total']),
            'filters' => $filters,
            'options' => $options,
        ]);
    }
}
