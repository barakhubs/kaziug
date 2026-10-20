<?php

declare(strict_types=1);

namespace App\Domain;

final class DeliveryCounts
{
    public function __construct(
        public readonly int $queued = 0,
        public readonly int $sent = 0,
        public readonly int $failed = 0,
    ) {
    }

    public function total(): int
    {
        return $this->queued + $this->sent + $this->failed;
    }
}
