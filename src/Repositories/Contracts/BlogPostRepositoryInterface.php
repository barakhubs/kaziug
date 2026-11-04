<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Domain\BlogPost;
use DateTimeImmutable;

interface BlogPostRepositoryInterface
{
    /** @return list<BlogPost> every post, most recently edited first */
    public function all(int $limit): array;

    /** @return array{items: list<BlogPost>, total: int} live posts, newest first */
    public function live(int $limit, int $offset): array;

    public function findById(int $id): ?BlogPost;

    public function findLiveBySlug(string $slug): ?BlogPost;

    /** @return list<BlogPost> other live posts, those sharing $categoryId first */
    public function liveRelated(int $exceptId, ?int $categoryId, int $limit): array;

    /** @return list<array{slug: string, updated_at: string}> every live post, for sitemap.xml */
    public function liveSitemapEntries(): array;

    public function slugExists(string $slug, ?int $exceptId = null): bool;

    /** @param string $bodyHtml already sanitised */
    public function insert(string $title, string $slug, string $excerpt, string $metaDescription, string $bodyHtml,
        ?int $jobCategoryId, string $coverAlt, bool $published, ?DateTimeImmutable $publishedAt): int;

    /** @param string $bodyHtml already sanitised */
    public function update(int $id, string $title, string $slug, string $excerpt, string $metaDescription, string $bodyHtml,
        ?int $jobCategoryId, string $coverAlt, bool $published, ?DateTimeImmutable $publishedAt): void;

    /** Replaces the cover and bumps its version. */
    public function putCover(int $id, string $mime, string $bytes): void;

    /**
     * @param bool $liveOnly true for the public site, which must not reveal draft covers
     * @return array{mime_type: string, content: string}|null
     */
    public function cover(int $id, bool $liveOnly): ?array;

    public function delete(int $id): void;
}
