<?php

declare(strict_types=1);

namespace App\Controllers\Public;

use App\Core\Http\Request;
use App\Core\Http\Responder;
use App\Core\Http\Response;
use App\Domain\JobSearchCriteria;
use App\Domain\PageContext;
use App\Domain\PageType;
use App\Services\Contracts\BlogReaderInterface;
use App\Services\Contracts\JobSearchServiceInterface;
use App\Services\Seo\StructuredData;

/** /blog and /blog/{slug}: live articles only. */
final class BlogController
{
    private const PER_PAGE = 9;
    private const RELATED_POSTS = 3;
    private const RELATED_JOBS = 5;

    public function __construct(
        private readonly BlogReaderInterface $blog,
        private readonly JobSearchServiceInterface $jobs,
        private readonly StructuredData $structuredData,
        private readonly Responder $responder,
    ) {
    }

    public function index(Request $request): Response
    {
        $page = max(1, min(1000, (int) $request->query('page', 1)));
        $request->setAttribute(PageContext::ATTRIBUTE, new PageContext(PageType::Blog));

        return $this->responder->view('public/blog/index', [
            'result' => $this->blog->page($page, self::PER_PAGE),
            'jsonLd' => [$this->structuredData->breadcrumbs([['Jobs', '/'], ['Blog', '/blog']])],
        ]);
    }

    public function show(Request $request): Response
    {
        $post = $this->blog->findBySlug((string) $request->attribute('slug'));
        $request->setAttribute(PageContext::ATTRIBUTE, new PageContext(PageType::Blog, null, $post->jobCategoryId));

        // Jobs from the article's category turn readers into applicants and link the blog to the job pages.
        $jobs = $post->jobCategorySlug === null
            ? []
            : $this->jobs->search(new JobSearchCriteria(categorySlug: $post->jobCategorySlug, sort: 'date'), self::RELATED_JOBS)->items;

        return $this->responder->view('public/blog/show', [
            'post' => $post,
            'relatedPosts' => $this->blog->related($post, self::RELATED_POSTS),
            'relatedJobs' => $jobs,
            'jsonLd' => [
                $this->structuredData->blogPosting($post),
                $this->structuredData->breadcrumbs([['Jobs', '/'], ['Blog', '/blog'], [$post->title, '/blog/' . $post->slug]]),
            ],
        ]);
    }
}
