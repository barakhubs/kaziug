<?php

declare(strict_types=1);

namespace Tests\Http;

use App\Console\Commands\SeedCommand;
use App\Console\Io;
use App\Core\Icon;
use Tests\Support\AdminHttpTestCase;

final class BlogTest extends AdminHttpTestCase
{
    /** @var list<string> */
    private array $tempFiles = [];

    protected function tearDown(): void
    {
        foreach ($this->tempFiles as $file) {
            @unlink($file);
        }
        parent::tearDown();
    }

    /** @return array<string, mixed> a $_FILES-style entry holding a small PNG */
    private function cover(): array
    {
        $image = imagecreatetruecolor(120, 63);
        ob_start();
        imagepng($image);
        $bytes = (string) ob_get_clean();
        $path = (string) tempnam(sys_get_temp_dir(), 'kz');
        file_put_contents($path, $bytes);
        $this->tempFiles[] = $path;

        return ['name' => 'cover.png', 'type' => 'image/png', 'tmp_name' => $path, 'error' => UPLOAD_ERR_OK, 'size' => strlen($bytes)];
    }

    /** @return array<string, string> */
    private function postForm(array $overrides = []): array
    {
        return [...[
            'title' => 'How to write a CV that gets interviews',
            'body_html' => '<h2>Start with a summary</h2><p>Keep it to <strong>three lines</strong>.</p><ul><li>Name</li><li>Phone</li></ul>',
            'excerpt' => 'A short guide to CVs that recruiters in Kampala read.',
            'meta_description' => 'Write a CV that gets you interviews in Uganda: structure, length and what to leave out.',
            'cover_alt' => 'A CV on a desk',
            'intent' => 'publish',
        ], ...$overrides];
    }

    private function postId(string $title = 'How to write a CV that gets interviews'): int
    {
        return (int) $this->db->fetchValue('SELECT id FROM blog_posts WHERE title = ?', [$title]);
    }

    private function logout(): void
    {
        $this->cookies = [];
    }

    public function testPublishingNeedsACoverAndMakesThePostLiveWithSeoMarkup(): void
    {
        $form = $this->get('/admin/blog/new')->body();
        $this->assertStringContainsString('/assets/vendor/quill/quill.js', $form);
        $this->assertStringContainsString('data-rich-editor="body_html"', $form);

        $noCover = $this->post('/admin/blog', $this->postForm());
        $this->assertStatus(422, $noCover);
        $this->assertStringContainsString('Add a cover image before publishing.', $noCover->body());

        $this->post('/admin/blog', $this->postForm(), files: ['cover' => $this->cover()]);
        $id = $this->postId();
        $this->assertSame('how-to-write-a-cv-that-gets-interviews', $this->db->fetchValue('SELECT slug FROM blog_posts WHERE id = ?', [$id]));
        $this->assertSame(1, $this->auditCount('blog.created'));

        $this->logout();
        $list = $this->get('/blog')->body();
        $this->assertStringContainsString('How to write a CV that gets interviews', $list);
        $this->assertStringContainsString('A short guide to CVs', $list);

        $page = $this->get('/blog/how-to-write-a-cv-that-gets-interviews');
        $this->assertStatus(200, $page);
        $html = $page->body();
        $this->assertStringContainsString('<h1>How to write a CV that gets interviews</h1>', $html);
        $this->assertStringContainsString('<h2>Start with a summary</h2>', $html);
        $this->assertStringContainsString('<meta name="description" content="Write a CV that gets you interviews in Uganda', $html);
        $this->assertStringContainsString('<link rel="canonical" href="http://localhost/blog/how-to-write-a-cv-that-gets-interviews">', $html);
        $this->assertStringContainsString('<meta property="og:type" content="article">', $html);
        $this->assertMatchesRegularExpression('#<meta property="og:image" content="[^"]*/media/blog/' . $id . '/cover\?v=1">#', $html);
        $this->assertStringContainsString('<meta property="og:image:alt" content="A CV on a desk">', $html);

        preg_match_all('#<script type="application/ld\+json">(.*?)</script>#s', $html, $m);
        $types = array_map(static fn (string $json): string => json_decode($json, true)['@type'], $m[1]);
        $this->assertSame(['BlogPosting', 'BreadcrumbList'], $types);
        $posting = json_decode($m[1][0], true);
        $this->assertSame('How to write a CV that gets interviews', $posting['headline']);
        $this->assertStringEndsWith("/media/blog/{$id}/cover?v=1", $posting['image']);
        $this->assertArrayHasKey('datePublished', $posting);

        $cover = $this->get("/media/blog/{$id}/cover");
        $this->assertStatus(200, $cover);
        $this->assertSame('image/png', $cover->header('Content-Type'));

        $this->assertStringContainsString('/blog/how-to-write-a-cv-that-gets-interviews', $this->get('/sitemap.xml')->body());
        $this->assertStringContainsString('[How to write a CV that gets interviews]', $this->get('/llms.txt')->body());
        $this->assertStringContainsString('<title>How to write a CV that gets interviews</title>', $this->get('/blog.rss')->body());
        $this->assertStringContainsString('<a href="/blog" class="active" aria-current="page">Blog</a>', $list);
    }

    public function testDraftsAndScheduledPostsStayHidden(): void
    {
        $this->post('/admin/blog', $this->postForm(['title' => 'Draft post', 'intent' => 'draft']));
        $draft = $this->postId('Draft post');
        $this->assertStringContainsString('<span class="pill draft">Draft</span>', $this->get('/admin/blog')->body());

        $tomorrow = (new \DateTimeImmutable('+1 day'))->format('Y-m-d\TH:i');
        $this->post('/admin/blog', $this->postForm(['title' => 'Later post', 'publish_at' => $tomorrow]), files: ['cover' => $this->cover()]);
        $later = $this->postId('Later post');
        $this->assertStringContainsString('<span class="pill scheduled">Scheduled</span>', $this->get('/admin/blog')->body());
        // The admin sees scheduled covers; visitors don't (below).
        $this->assertStatus(200, $this->get("/admin/blog/{$later}/cover"));

        $this->logout();
        $this->assertStringNotContainsString('Draft post', $this->get('/blog')->body());
        $this->assertStringNotContainsString('Later post', $this->get('/blog')->body());
        $this->assertStatus(404, $this->get('/blog/draft-post'));
        $this->assertStatus(404, $this->get('/blog/later-post'));
        $this->assertStatus(404, $this->get("/media/blog/{$later}/cover"));
        $this->assertStringNotContainsString('later-post', $this->get('/sitemap.xml')->body());
        $this->assertRedirect('/admin/login', $this->get("/admin/blog/{$draft}/cover"));

        // Its date arrives: the scheduled post goes live with no further action.
        $this->db->execute('UPDATE blog_posts SET published_at = NOW(6) - INTERVAL 1 MINUTE WHERE id = ?', [$later]);
        $this->assertStatus(200, $this->get('/blog/later-post'));
    }

    public function testArticleHtmlIsSanitised(): void
    {
        $this->post('/admin/blog', $this->postForm([
            'body_html' => '<p onclick="steal()">Hello <a href="javascript:alert(1)">bad</a> <a href="https://example.com">good</a></p>'
                . '<script>alert(1)</script><img src=x onerror="alert(1)"><iframe src="https://evil.example"></iframe>',
        ]), files: ['cover' => $this->cover()]);

        $stored = (string) $this->db->fetchValue('SELECT body_html FROM blog_posts');
        $this->assertStringNotContainsString('script', $stored);
        $this->assertStringNotContainsString('onclick', $stored);
        $this->assertStringNotContainsString('javascript:', $stored);
        $this->assertStringNotContainsString('<img', $stored);
        $this->assertStringNotContainsString('iframe', $stored);
        $this->assertStringContainsString('<a href="https://example.com">good</a>', $stored);

        $empty = $this->post('/admin/blog', $this->postForm(['title' => 'Empty', 'body_html' => '<p><br></p><script>x</script>']));
        $this->assertStatus(422, $empty);
        $this->assertStringContainsString('Write the article.', $empty->body());
    }

    public function testSlugsAreUniqueAndKeptWhenCleared(): void
    {
        $this->post('/admin/blog', $this->postForm(['intent' => 'draft']));
        $this->post('/admin/blog', $this->postForm(['intent' => 'draft']));
        $this->assertSame(
            ['how-to-write-a-cv-that-gets-interviews', 'how-to-write-a-cv-that-gets-interviews-2'],
            array_column($this->db->fetchAll('SELECT slug FROM blog_posts ORDER BY id'), 'slug'),
        );

        $second = (int) $this->db->fetchValue('SELECT max(id) FROM blog_posts');
        $taken = $this->post("/admin/blog/{$second}", $this->postForm(['intent' => 'draft', 'slug' => 'how-to-write-a-cv-that-gets-interviews']));
        $this->assertStatus(422, $taken);
        $this->assertStringContainsString('Another post already uses this URL.', $taken->body());

        $this->post("/admin/blog/{$second}", $this->postForm(['intent' => 'draft', 'title' => 'Renamed', 'slug' => '']));
        $this->assertSame('how-to-write-a-cv-that-gets-interviews-2', $this->db->fetchValue('SELECT slug FROM blog_posts WHERE id = ?', [$second]));
    }

    public function testRelatedJobsUnpublishAndDelete(): void
    {
        $this->container->get(SeedCommand::class)->run(['--only=jobs'], new Io([], echo: false));
        $category = $this->db->fetchOne('SELECT c.id, c.name FROM job_categories c JOIN jobs j ON j.category_id = c.id LIMIT 1');
        $this->post('/admin/blog', $this->postForm(['job_category_id' => (string) $category['id']]), files: ['cover' => $this->cover()]);
        $id = $this->postId();

        $page = $this->get('/blog/how-to-write-a-cv-that-gets-interviews')->body();
        $this->assertStringContainsString('<h2>' . e($category['name']) . ' jobs</h2>', $page);
        $jobTitle = (string) $this->db->fetchValue("SELECT title FROM jobs WHERE category_id = ? AND status = 'published' ORDER BY published_at DESC LIMIT 1", [$category['id']]);
        $this->assertStringContainsString(e($jobTitle), $page);

        $this->post("/admin/blog/{$id}", $this->postForm(['intent' => 'draft']));
        $this->assertStatus(404, $this->get('/blog/how-to-write-a-cv-that-gets-interviews'));

        $this->post("/admin/blog/{$id}/delete");
        $this->assertNull($this->db->fetchValue('SELECT id FROM blog_posts WHERE id = ?', [$id]));
        $this->assertSame(1, $this->auditCount('blog.deleted'));
    }

    public function testBlogPageViewsAreCountedAsBlogPages(): void
    {
        $this->post('/admin/blog', $this->postForm(), files: ['cover' => $this->cover()]);
        $this->logout();
        $this->headers['user-agent'] = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/130.0';

        $this->get('/blog/how-to-write-a-cv-that-gets-interviews');
        $this->assertSame('blog', $this->db->fetchValue("SELECT page_type FROM analytics_events WHERE path = '/blog/how-to-write-a-cv-that-gets-interviews'"));
    }

    public function testSidebarIsGroupedAndJobPagesOfferANewJob(): void
    {
        $dashboard = $this->get('/admin')->body();
        foreach (['Jobs', 'Content', 'Email', 'System'] as $heading) {
            $this->assertMatchesRegularExpression('#<p class="nav-heading" id="nav-group-\d">' . $heading . '</p>#', $dashboard);
        }
        $this->assertStringContainsString('<a class="nav-quick" href="/admin/jobs/new">' . Icon::svg('solid/plus') . '<span>New job</span></a>', $dashboard);
        $this->assertStringContainsString(
            '<a class="nav-btn active" href="/admin/blog" aria-current="page">' . Icon::svg('solid/newspaper') . '<span>Blog</span></a>',
            $this->get('/admin/blog')->body(),
        );
        // Every sidebar link has an icon.
        preg_match_all('#<a class="nav-btn[^"]*"[^>]*>(.*?)</a>#s', $dashboard, $links);
        $this->assertCount(13, $links[1]);
        foreach ($links[1] as $inner) {
            $this->assertStringStartsWith('<svg class="icon"', $inner);
        }

        $this->container->get(SeedCommand::class)->run(['--only=jobs'], new Io([], echo: false));
        $jobId = (int) $this->db->fetchValue('SELECT min(id) FROM jobs');
        $this->assertStringContainsString('<a class="btn-ghost" href="/admin/jobs/new">+ New job</a>', $this->get("/admin/jobs/{$jobId}")->body());
        $this->assertStringContainsString('<a class="btn-ghost" href="/admin/jobs/new">+ New job</a>', $this->get("/admin/jobs/{$jobId}/edit")->body());
    }
}
