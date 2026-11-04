<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database\Database;
use App\Core\Database\DbTime;
use App\Domain\BlogPost;
use App\Repositories\Contracts\BlogPostRepositoryInterface;
use DateTimeImmutable;
use PDO;

final class MysqlBlogPostRepository implements BlogPostRepositoryInterface
{
    /** The single definition of "publicly visible": published and its date has come. */
    private const LIVE = "p.status = 'published' AND p.published_at <= NOW(6)";

    // The cover bytes are never selected here; they are only read by cover().
    private const SELECT = 'SELECT p.id, p.title, p.slug, p.excerpt, p.meta_description, p.body_html, p.status, p.published_at,
        p.job_category_id, c.name AS category_name, c.slug AS category_slug, p.cover_mime IS NOT NULL AS has_cover,
        p.cover_version, p.cover_alt, p.created_at, p.updated_at
        FROM blog_posts p LEFT JOIN job_categories c ON c.id = p.job_category_id';

    public function __construct(private readonly Database $db)
    {
    }

    public function all(int $limit): array
    {
        return array_map(self::map(...), $this->db->fetchAll(self::SELECT . ' ORDER BY p.updated_at DESC, p.id DESC LIMIT ?', [$limit]));
    }

    public function live(int $limit, int $offset): array
    {
        $total = (int) $this->db->fetchValue('SELECT count(*) FROM blog_posts p WHERE ' . self::LIVE);
        $rows = $this->db->fetchAll(
            self::SELECT . ' WHERE ' . self::LIVE . ' ORDER BY p.published_at DESC, p.id DESC LIMIT ? OFFSET ?',
            [$limit, $offset],
        );

        return ['items' => array_map(self::map(...), $rows), 'total' => $total];
    }

    public function findById(int $id): ?BlogPost
    {
        $row = $this->db->fetchOne(self::SELECT . ' WHERE p.id = ?', [$id]);

        return $row === null ? null : self::map($row);
    }

    public function findLiveBySlug(string $slug): ?BlogPost
    {
        $row = $this->db->fetchOne(self::SELECT . ' WHERE p.slug = ? AND ' . self::LIVE, [$slug]);

        return $row === null ? null : self::map($row);
    }

    public function liveRelated(int $exceptId, ?int $categoryId, int $limit): array
    {
        return array_map(self::map(...), $this->db->fetchAll(
            self::SELECT . ' WHERE ' . self::LIVE . ' AND p.id <> ?
             ORDER BY (p.job_category_id <=> ?) DESC, p.published_at DESC, p.id DESC LIMIT ?',
            [$exceptId, $categoryId, $limit],
        ));
    }

    public function liveSitemapEntries(): array
    {
        return array_map(static fn (array $r): array => ['slug' => (string) $r['slug'], 'updated_at' => (string) $r['updated_at']], $this->db->fetchAll(
            'SELECT p.slug, p.updated_at FROM blog_posts p WHERE ' . self::LIVE . ' ORDER BY p.published_at DESC LIMIT 50000',
        ));
    }

    public function slugExists(string $slug, ?int $exceptId = null): bool
    {
        return (bool) $this->db->fetchValue(
            'SELECT EXISTS (SELECT 1 FROM blog_posts WHERE slug = ? AND id <> ?)',
            [$slug, $exceptId ?? 0],
        );
    }

    public function insert(string $title, string $slug, string $excerpt, string $metaDescription, string $bodyHtml,
        ?int $jobCategoryId, string $coverAlt, bool $published, ?DateTimeImmutable $publishedAt): int
    {
        return $this->db->insert(
            'INSERT INTO blog_posts (title, slug, excerpt, meta_description, body_html, job_category_id, cover_alt, status, published_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [$title, $slug, $excerpt, $metaDescription, $bodyHtml, $jobCategoryId, $coverAlt, $published ? 'published' : 'draft', DbTime::toDb($publishedAt)],
        );
    }

    public function update(int $id, string $title, string $slug, string $excerpt, string $metaDescription, string $bodyHtml,
        ?int $jobCategoryId, string $coverAlt, bool $published, ?DateTimeImmutable $publishedAt): void
    {
        $this->db->execute(
            'UPDATE blog_posts SET title = ?, slug = ?, excerpt = ?, meta_description = ?, body_html = ?, job_category_id = ?,
                cover_alt = ?, status = ?, published_at = ?
             WHERE id = ?',
            [$title, $slug, $excerpt, $metaDescription, $bodyHtml, $jobCategoryId, $coverAlt, $published ? 'published' : 'draft', DbTime::toDb($publishedAt), $id],
        );
    }

    public function putCover(int $id, string $mime, string $bytes): void
    {
        $statement = $this->db->pdo()->prepare(
            'UPDATE blog_posts SET cover_mime = :mime, cover = :cover, cover_version = cover_version + 1 WHERE id = :id',
        );
        $statement->bindValue('mime', $mime);
        $statement->bindValue('cover', $bytes, PDO::PARAM_LOB);
        $statement->bindValue('id', $id, PDO::PARAM_INT);
        $statement->execute();
    }

    public function cover(int $id, bool $liveOnly): ?array
    {
        $row = $this->db->fetchOne(
            'SELECT p.cover_mime, p.cover FROM blog_posts p WHERE p.id = ? AND p.cover_mime IS NOT NULL'
            . ($liveOnly ? ' AND ' . self::LIVE : ''),
            [$id],
        );

        return $row === null ? null : ['mime_type' => (string) $row['cover_mime'], 'content' => (string) $row['cover']];
    }

    public function delete(int $id): void
    {
        $this->db->execute('DELETE FROM blog_posts WHERE id = ?', [$id]);
    }

    /** @param array<string, mixed> $row */
    private static function map(array $row): BlogPost
    {
        return new BlogPost(
            (int) $row['id'],
            (string) $row['title'],
            (string) $row['slug'],
            (string) $row['excerpt'],
            (string) $row['meta_description'],
            (string) $row['body_html'],
            $row['status'] === 'published',
            DbTime::fromDb($row['published_at']),
            $row['job_category_id'] === null ? null : (int) $row['job_category_id'],
            $row['category_name'] === null ? null : (string) $row['category_name'],
            $row['category_slug'] === null ? null : (string) $row['category_slug'],
            (bool) $row['has_cover'],
            (int) $row['cover_version'],
            (string) $row['cover_alt'],
            DbTime::fromDb($row['created_at']) ?? new DateTimeImmutable(),
            DbTime::fromDb($row['updated_at']) ?? new DateTimeImmutable(),
        );
    }
}
