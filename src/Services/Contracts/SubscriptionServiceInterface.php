<?php

declare(strict_types=1);

namespace App\Services\Contracts;

use App\Domain\Subscriber;

/** The public double opt-in flow. */
interface SubscriptionServiceInterface
{
    /**
     * Records consent and sends a confirmation email. Always looks the same from outside
     * (no hint whether the address was already subscribed).
     * @return int 0 when accepted, otherwise the seconds the visitor's IP must wait (rate limited)
     */
    public function subscribe(string $email, ?string $name, string $ip): int;

    /** Activates the subscriber for a valid, unexpired confirmation token; null otherwise. */
    public function confirm(string $token): ?Subscriber;

    public function findByUnsubscribeToken(string $token): ?Subscriber;

    /** Idempotent. Returns false when the token is unknown. */
    public function unsubscribe(string $token): bool;
}
