<?php

declare(strict_types=1);

namespace App\Services\Contracts;

use App\Domain\Actor;
use App\Domain\BlogPost;
use App\Domain\BlogPostInput;

/** Back-office management of blog posts. Public reads are in BlogReaderInterface. */
interface BlogServiceInterface
{
    /** @return list<BlogPost> */
    public function all(): array;

    public function get(int $id): BlogPost;

    /**
     * @param string|null $coverBytes an uploaded cover, or null for none
     * @param bool $publish false saves a draft; true publishes now or at $input->publishAt (a cover is then required)
     */
    public function create(BlogPostInput $input, ?string $coverBytes, bool $publish, Actor $actor): BlogPost;

    /** @param string|null $coverBytes a replacement cover, or null to keep the current one */
    public function update(int $id, BlogPostInput $input, ?string $coverBytes, bool $publish, Actor $actor): void;

    public function delete(int $id, Actor $actor): void;

    /**
     * @param bool $liveOnly true for the public site (no draft covers)
     * @return array{mime_type: string, content: string}|null
     */
    public function cover(int $id, bool $liveOnly): ?array;
}
