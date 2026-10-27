<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use DateTimeImmutable;

/** The random salt that makes visitor hashes unlinkable across days (analytics_salts). */
interface VisitorSaltRepositoryInterface
{
    /** The salt for $day, created on first use. Salts for earlier days are deleted at the same time. */
    public function saltFor(DateTimeImmutable $day): string;
}
