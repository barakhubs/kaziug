<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Domain\Newsletter;
use App\Domain\NewsletterStatus;
use DateTimeImmutable;

interface NewsletterRepositoryInterface
{
    public function findById(int $id): ?Newsletter;

    /** @return array{items: list<Newsletter>, total: int} newest first */
    public function paginate(int $limit, int $offset): array;

    public function latest(): ?Newsletter;

    /** @param array{subject: string, preheader: string, body_html: string, body_text: string} $data */
    public function create(array $data, ?int $adminId): int;

    /** @param array{subject: string, preheader: string, body_html: string, body_text: string} $data */
    public function update(int $id, array $data): void;

    /**
     * Moves the status only if the newsletter is currently in one of $from (atomic guard
     * against double sends). Returns whether it changed.
     * @param list<NewsletterStatus> $from
     */
    public function transition(int $id, array $from, NewsletterStatus $to, ?DateTimeImmutable $scheduledAt = null): bool;

    /** @return list<int> scheduled newsletters whose time has come */
    public function dueScheduledIds(DateTimeImmutable $now): array;

    /** @return list<int> newsletters in "sending" */
    public function sendingIds(): array;

    public function markFinished(int $id, NewsletterStatus $status): void;

    public function delete(int $id): void;
}
