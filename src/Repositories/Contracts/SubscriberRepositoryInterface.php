<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Domain\Subscriber;

interface SubscriberRepositoryInterface
{
    public function findById(int $id): ?Subscriber;

    /** Case-insensitive. */
    public function findByEmail(string $email): ?Subscriber;

    /** A pending subscriber whose confirmation email was sent less than $maxAgeSeconds ago. */
    public function findPendingByConfirmHash(string $hash, int $maxAgeSeconds): ?Subscriber;

    public function findByUnsubscribeToken(string $token): ?Subscriber;

    /** New pending subscriber with consent recorded now. */
    public function createPending(string $email, ?string $name, string $consentIp, string $confirmHash, string $unsubscribeToken): Subscriber;

    /** Back to pending with fresh consent and a new confirmation token (re-subscribe or resend). */
    public function restartConfirmation(int $id, ?string $name, string $consentIp, string $confirmHash): void;

    public function activate(int $id): void;

    public function unsubscribe(int $id): void;

    public function delete(int $id): void;

    /**
     * @param array{q?: string, status?: string} $filters
     * @return array{items: list<Subscriber>, total: int}
     */
    public function search(array $filters, int $limit, int $offset): array;

    /** @return array<string, int> */
    public function countByStatus(): array;

    /** @return iterable<Subscriber> */
    public function each(?string $status): iterable;

    /**
     * @param list<string> $emails lower-case
     * @return list<string> those already present (lower-case)
     */
    public function existingEmails(array $emails): array;

    /**
     * Inserts active subscribers whose consent was given elsewhere; existing emails are skipped.
     * @param list<array{email: string, name: ?string, unsubscribe_token: string}> $rows
     * @return int rows inserted
     */
    public function insertImported(array $rows): int;
}
