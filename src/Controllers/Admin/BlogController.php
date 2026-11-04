<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Http\Request;
use App\Core\Http\Responder;
use App\Core\Http\Response;
use App\Domain\BlogPost;
use App\Domain\DomainException;
use App\Services\Contracts\BlogServiceInterface;
use App\Services\Contracts\CategoryServiceInterface;
use App\Services\ImageInspector;
use App\Validation\BlogPostValidator;

final class BlogController
{
    use AdminContext;

    public function __construct(
        private readonly BlogServiceInterface $blog,
        private readonly CategoryServiceInterface $categories,
        private readonly BlogPostValidator $validator,
        private readonly LogoUploadReader $uploads,
        private readonly Responder $responder,
    ) {
    }

    public function index(Request $request): Response
    {
        return $this->responder->view('admin/blog/index', ['posts' => $this->blog->all()]);
    }

    public function create(Request $request): Response
    {
        return $this->form(null, [], []);
    }

    public function store(Request $request): Response
    {
        [$input, $errors] = $this->validator->validate($request->all());
        $cover = $this->uploads->read($request->file('cover'), $errors, 'cover', ImageInspector::MAX_COVER_BYTES);
        $publish = $request->input('intent') === 'publish';
        if ($input === null || $errors !== []) {
            return $this->form(null, $request->all(), $errors, 422);
        }

        try {
            $post = $this->blog->create($input, $cover, $publish, $this->actor($request));
        } catch (DomainException $e) {
            return $this->form(null, $request->all(), [$e->field ?? 'title' => $e->getMessage()], 422);
        }

        return $this->responder->redirectWith($request, "/admin/blog/{$post->id}/edit", 'success', $this->savedMessage($post));
    }

    public function edit(Request $request): Response
    {
        $post = $this->blog->get($this->id($request));

        return $this->form($post, self::valuesFrom($post), []);
    }

    public function update(Request $request): Response
    {
        $post = $this->blog->get($this->id($request));
        [$input, $errors] = $this->validator->validate($request->all());
        $cover = $this->uploads->read($request->file('cover'), $errors, 'cover', ImageInspector::MAX_COVER_BYTES);
        $publish = $request->input('intent') === 'publish';
        if ($input === null || $errors !== []) {
            return $this->form($post, $request->all(), $errors, 422);
        }

        try {
            $this->blog->update($post->id, $input, $cover, $publish, $this->actor($request));
        } catch (DomainException $e) {
            return $this->form($post, $request->all(), [$e->field ?? 'title' => $e->getMessage()], 422);
        }

        return $this->responder->redirectWith($request, "/admin/blog/{$post->id}/edit", 'success', $this->savedMessage($this->blog->get($post->id)));
    }

    public function delete(Request $request): Response
    {
        $this->blog->delete($this->id($request), $this->actor($request));

        return $this->responder->redirectWith($request, '/admin/blog', 'success', 'Post deleted.');
    }

    private function savedMessage(BlogPost $post): string
    {
        return match ($post->statusLabel()) {
            'Published' => 'Post saved and live on the blog.',
            'Scheduled' => 'Post scheduled. It goes live on ' . $post->publishedAt?->format('j M Y \a\t H:i') . '.',
            default => 'Draft saved.',
        };
    }

    /** @return array<string, string> */
    private static function valuesFrom(BlogPost $post): array
    {
        return [
            'title' => $post->title,
            'slug' => $post->slug,
            'excerpt' => $post->excerpt,
            'meta_description' => $post->metaDescription,
            'body_html' => $post->bodyHtml,
            'job_category_id' => $post->jobCategoryId === null ? '' : (string) $post->jobCategoryId,
            'cover_alt' => $post->coverAlt,
            'publish_at' => $post->publishedAt?->format('Y-m-d\TH:i') ?? '',
        ];
    }

    /**
     * @param array<string, mixed> $values
     * @param array<string, string> $errors
     */
    private function form(?BlogPost $post, array $values, array $errors, int $status = 200): Response
    {
        return $this->responder->view('admin/blog/form', [
            'post' => $post,
            'values' => $values,
            'errors' => $errors,
            'categories' => $this->categories->all(),
        ], $status);
    }
}
