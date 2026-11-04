<?php

declare(strict_types=1);

namespace App\Services\Contracts;

use App\Domain\BlogPost;
use App\Domain\Paginated;

/** What the public site may see of the blog: live posts only. */
interface BlogReaderInterface
{
    /** @return Paginated<BlogPost> */
    public function page(int $page, int $perPage): Paginated;

    /** @throws \App\Domain\NotFoundException when no live post has this slug */
    public function findBySlug(string $slug): BlogPost;

    /** @return list<BlogPost> other live posts, same job category first */
    public function related(BlogPost $post, int $limit): array;

    /** @return list<BlogPost> the newest live posts */
    public function latest(int $limit): array;

    /** @return list<array{slug: string, updated_at: string}> */
    public function sitemapEntries(): array;
}
