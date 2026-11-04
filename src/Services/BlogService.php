<?php

declare(strict_types=1);

namespace App\Services;

use App\Domain\Actor;
use App\Domain\BlogPost;
use App\Domain\BlogPostInput;
use App\Domain\DomainException;
use App\Domain\NotFoundException;
use App\Domain\Slug;
use App\Repositories\Contracts\BlogPostRepositoryInterface;
use App\Repositories\Contracts\CategoryRepositoryInterface;
use App\Repositories\Contracts\TransactionManagerInterface;
use App\Services\Contracts\AuditServiceInterface;
use App\Services\Contracts\BlogServiceInterface;
use DateTimeImmutable;

final class BlogService implements BlogServiceInterface
{
    private const LIST_LIMIT = 500;

    public function __construct(
        private readonly BlogPostRepositoryInterface $posts,
        private readonly CategoryRepositoryInterface $categories,
        private readonly HtmlSanitizer $sanitizer,
        private readonly ImageInspector $images,
        private readonly TransactionManagerInterface $tx,
        private readonly AuditServiceInterface $audit,
    ) {
    }

    public function all(): array
    {
        return $this->posts->all(self::LIST_LIMIT);
    }

    public function get(int $id): BlogPost
    {
        return $this->posts->findById($id) ?? throw new NotFoundException('Blog post not found.');
    }

    public function create(BlogPostInput $input, ?string $coverBytes, bool $publish, Actor $actor): BlogPost
    {
        [$body, $mime] = $this->prepare($input, $coverBytes, $publish, false);
        $slug = $this->slugFor($input, null);
        $publishedAt = $publish ? ($input->publishAt ?? new DateTimeImmutable()) : $input->publishAt;

        $id = $this->tx->transactional(function () use ($input, $slug, $body, $publish, $publishedAt, $mime, $coverBytes): int {
            $id = $this->posts->insert($input->title, $slug, $input->excerpt, $input->metaDescription, $body,
                $input->jobCategoryId, $input->coverAlt, $publish, $publishedAt);
            if ($mime !== null && $coverBytes !== null) {
                $this->posts->putCover($id, $mime, $coverBytes);
            }

            return $id;
        });
        $this->audit->record($actor, 'blog.created', 'blog_post', $id, ['title' => $input->title, 'status' => $publish ? 'published' : 'draft']);

        return $this->get($id);
    }

    public function update(int $id, BlogPostInput $input, ?string $coverBytes, bool $publish, Actor $actor): void
    {
        $current = $this->get($id);
        [$body, $mime] = $this->prepare($input, $coverBytes, $publish, $current->hasCover);
        $slug = $this->slugFor($input, $current);
        // An explicit date wins; otherwise a post keeps its date, and one published for the first time gets "now".
        $publishedAt = $input->publishAt ?? $current->publishedAt ?? ($publish ? new DateTimeImmutable() : null);

        $this->tx->transactional(function () use ($id, $input, $slug, $body, $publish, $publishedAt, $mime, $coverBytes): void {
            $this->posts->update($id, $input->title, $slug, $input->excerpt, $input->metaDescription, $body,
                $input->jobCategoryId, $input->coverAlt, $publish, $publishedAt);
            if ($mime !== null && $coverBytes !== null) {
                $this->posts->putCover($id, $mime, $coverBytes);
            }
        });
        $this->audit->record($actor, 'blog.updated', 'blog_post', $id, array_filter([
            'title' => $input->title,
            'status' => $publish ? 'published' : 'draft',
            'cover_replaced' => $mime !== null ? true : null,
        ], static fn (mixed $v): bool => $v !== null));
    }

    public function delete(int $id, Actor $actor): void
    {
        $post = $this->get($id);
        $this->posts->delete($id);
        $this->audit->record($actor, 'blog.deleted', 'blog_post', $id, ['title' => $post->title]);
    }

    public function cover(int $id, bool $liveOnly): ?array
    {
        return $this->posts->cover($id, $liveOnly);
    }

    /**
     * Checks the rules shared by create and update.
     * @return array{0: string, 1: ?string} the sanitised body and the new cover's MIME type (null when none)
     */
    private function prepare(BlogPostInput $input, ?string $coverBytes, bool $publish, bool $hasCover): array
    {
        $body = $this->sanitizer->sanitize($input->bodyHtml);
        if (trim(strip_tags($body)) === '') {
            throw new DomainException('Write the article.', 'body_html');
        }
        if ($input->jobCategoryId !== null && $this->categories->findById($input->jobCategoryId) === null) {
            throw new DomainException('Choose a job category from the list.', 'job_category_id');
        }
        $mime = $coverBytes === null ? null : $this->images->assertAcceptableCover($coverBytes);
        if ($publish && $mime === null && !$hasCover) {
            throw new DomainException('Add a cover image before publishing.', 'cover');
        }

        return [$body, $mime];
    }

    private function slugFor(BlogPostInput $input, ?BlogPost $current): string
    {
        if ($input->slug !== null) {
            if ($this->posts->slugExists($input->slug, $current?->id)) {
                throw new DomainException('Another post already uses this URL.', 'slug');
            }

            return $input->slug;
        }
        // Keep an existing URL when the slug field is cleared, so links keep working.
        if ($current !== null) {
            return $current->slug;
        }

        return Slug::unique(Slug::from($input->title), fn (string $s): bool => $this->posts->slugExists($s));
    }
}
