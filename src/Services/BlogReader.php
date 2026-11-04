<?php

declare(strict_types=1);

namespace App\Services;

use App\Domain\BlogPost;
use App\Domain\NotFoundException;
use App\Domain\Paginated;
use App\Domain\Pagination;
use App\Repositories\Contracts\BlogPostRepositoryInterface;
use App\Services\Contracts\BlogReaderInterface;

final class BlogReader implements BlogReaderInterface
{
    public function __construct(private readonly BlogPostRepositoryInterface $posts)
    {
    }

    public function page(int $page, int $perPage): Paginated
    {
        $first = $this->posts->live($perPage, max(0, $page - 1) * $perPage);
        $pagination = new Pagination($page, $perPage, $first['total']);
        if ($pagination->page !== $page) {
            // Requested page was past the end: show the last page instead.
            $first = $this->posts->live($perPage, $pagination->offset());
        }

        return new Paginated($first['items'], $pagination);
    }

    public function findBySlug(string $slug): BlogPost
    {
        return $this->posts->findLiveBySlug($slug) ?? throw new NotFoundException('This article is not available.');
    }

    public function related(BlogPost $post, int $limit): array
    {
        return $this->posts->liveRelated($post->id, $post->jobCategoryId, $limit);
    }

    public function latest(int $limit): array
    {
        return $this->posts->live($limit, 0)['items'];
    }

    public function sitemapEntries(): array
    {
        return $this->posts->liveSitemapEntries();
    }
}
