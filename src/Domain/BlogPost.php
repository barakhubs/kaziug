<?php

declare(strict_types=1);

namespace App\Domain;

use App\Core\Format;
use DateTimeImmutable;

/** A blog article. bodyHtml has already been through HtmlSanitizer, so it is safe to print. */
final class BlogPost
{
    private const WORDS_PER_MINUTE = 200;

    public function __construct(
        public readonly int $id,
        public readonly string $title,
        public readonly string $slug,
        public readonly string $excerpt,
        public readonly string $metaDescription,
        public readonly string $bodyHtml,
        public readonly bool $isPublished,
        /** When the post goes (or went) live; in the future for a scheduled post. */
        public readonly ?DateTimeImmutable $publishedAt,
        public readonly ?int $jobCategoryId,
        public readonly ?string $jobCategoryName,
        public readonly ?string $jobCategorySlug,
        public readonly bool $hasCover,
        public readonly int $coverVersion,
        public readonly string $coverAlt,
        public readonly DateTimeImmutable $createdAt,
        public readonly DateTimeImmutable $updatedAt,
    ) {
    }

    public function isLive(?DateTimeImmutable $now = null): bool
    {
        return $this->isPublished && $this->publishedAt !== null && $this->publishedAt <= ($now ?? new DateTimeImmutable());
    }

    public function isScheduled(?DateTimeImmutable $now = null): bool
    {
        return $this->isPublished && !$this->isLive($now);
    }

    /** Draft, Scheduled or Published, as the admin sees it. */
    public function statusLabel(): string
    {
        return match (true) {
            !$this->isPublished => 'Draft',
            $this->isScheduled() => 'Scheduled',
            default => 'Published',
        };
    }

    /** Public cover URL (served only while the post is live). */
    public function coverUrl(): ?string
    {
        return $this->hasCover ? "/media/blog/{$this->id}/cover?v={$this->coverVersion}" : null;
    }

    /** Cover URL for the back office, which also works for drafts. */
    public function adminCoverUrl(): ?string
    {
        return $this->hasCover ? "/admin/blog/{$this->id}/cover?v={$this->coverVersion}" : null;
    }

    /** The search snippet: the meta description, else the excerpt, else the start of the article. */
    public function description(): string
    {
        return match (true) {
            $this->metaDescription !== '' => $this->metaDescription,
            $this->excerpt !== '' => $this->excerpt,
            default => Format::excerpt($this->plainText()),
        };
    }

    public function readingMinutes(): int
    {
        $words = count(preg_split('/\s+/u', $this->plainText(), -1, PREG_SPLIT_NO_EMPTY) ?: []);

        return max(1, (int) ceil($words / self::WORDS_PER_MINUTE));
    }

    public function plainText(): string
    {
        $text = html_entity_decode(strip_tags(str_replace(['</p>', '<br>', '</li>', '</h2>', '</h3>'], ' ', $this->bodyHtml)), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim((string) preg_replace('/\s+/u', ' ', $text));
    }
}
