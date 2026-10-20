<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

/** A named, process-wide lock (e.g. "only one newsletter worker at a time"). */
interface LockInterface
{
    public function tryAcquire(string $name): bool;

    public function release(string $name): void;
}
