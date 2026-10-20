<?php

declare(strict_types=1);

namespace App\Services\Contracts;

interface HealthCheckInterface
{
    /** @return array{ok: bool, checks: array<string, bool>} */
    public function check(): array;
}
