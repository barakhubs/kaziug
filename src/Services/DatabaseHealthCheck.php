<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database\Database;
use App\Core\Log\LoggerInterface;
use App\Services\Contracts\HealthCheckInterface;
use Throwable;

final class DatabaseHealthCheck implements HealthCheckInterface
{
    public function __construct(
        private readonly Database $db,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function check(): array
    {
        try {
            $database = (int) $this->db->fetchValue('SELECT 1') === 1;
        } catch (Throwable $e) {
            $this->logger->error('Health check: database unreachable', ['error' => $e->getMessage()]);
            $database = false;
        }

        return ['ok' => $database, 'checks' => ['database' => $database]];
    }
}
