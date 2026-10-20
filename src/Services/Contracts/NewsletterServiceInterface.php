<?php

declare(strict_types=1);

namespace App\Services\Contracts;

use App\Domain\Actor;
use App\Domain\DeliveryCounts;
use App\Domain\Newsletter;
use App\Domain\Paginated;
use DateTimeImmutable;

/** Back-office newsletter management. Sending is only ever queued here; the worker sends. */
interface NewsletterServiceInterface
{
    /** @return Paginated<Newsletter> */
    public function paginate(int $page, int $perPage): Paginated;

    public function get(int $id): Newsletter;

    public function latest(): ?Newsletter;

    /**
     * @param array{subject: string, preheader: string, body_html: string, body_text: ?string} $data
     *        body_html is sanitised here; an empty body_text is generated from it
     */
    public function create(array $data, Actor $actor): Newsletter;

    /** @param array{subject: string, preheader: string, body_html: string, body_text: ?string} $data */
    public function update(int $id, array $data, Actor $actor): void;

    public function delete(int $id, Actor $actor): void;

    /** The full email as a subscriber would see it (sample unsubscribe link). */
    public function previewHtml(int $id): string;

    /** @throws \App\Mail\MailException */
    public function sendTest(int $id, string $to, Actor $actor): void;

    /**
     * Queues one delivery per active subscriber and hands the newsletter to the worker.
     * @return int deliveries queued
     * @throws \App\Domain\DomainException
     */
    public function sendNow(int $id, Actor $actor): int;

    /** @throws \App\Domain\DomainException */
    public function schedule(int $id, DateTimeImmutable $at, Actor $actor): void;

    public function unschedule(int $id, Actor $actor): void;

    public function counts(int $id): DeliveryCounts;

    /** @return list<array{email: string, attempts: int, last_error: ?string}> */
    public function permanentFailures(int $id): array;
}
