<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\Contracts\VisitorSaltRepositoryInterface;
use DateTimeImmutable;

/**
 * Turns IP address + user agent into an anonymous visitor id that is only stable for one calendar
 * day. The salt is random and deleted when the day ends, so ids can't be linked across days or
 * reversed afterwards; the IP address and user agent themselves are never stored.
 */
final class VisitorHasher
{
    public function __construct(private readonly VisitorSaltRepositoryInterface $salts)
    {
    }

    public function hash(string $ip, string $userAgent, ?DateTimeImmutable $now = null): string
    {
        $day = ($now ?? new DateTimeImmutable())->setTimezone(new \DateTimeZone(date_default_timezone_get()))->setTime(0, 0);

        return substr(hash_hmac('sha256', $ip . "\n" . $userAgent, $this->salts->saltFor($day)), 0, 32);
    }
}
