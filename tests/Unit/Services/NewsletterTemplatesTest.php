<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Domain\ExperienceLevel;
use App\Domain\Job;
use App\Domain\JobStatus;
use App\Domain\JobType;
use App\Domain\SiteSettings;
use App\Mail\EmailStyler;
use App\Services\HtmlSanitizer;
use App\Services\NewsletterTemplates\AnnouncementTemplate;
use App\Services\NewsletterTemplates\CvHelpTemplate;
use App\Services\NewsletterTemplates\JobEmailHtml;
use App\Services\NewsletterTemplates\JobSpotlightTemplate;
use App\Services\NewsletterTemplates\LatestJobsTemplate;
use App\Services\NewsletterTemplates\NewsletterTemplateInterface;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class NewsletterTemplatesTest extends TestCase
{
    /** @return iterable<string, array{NewsletterTemplateInterface}> */
    public static function templates(): iterable
    {
        $html = new JobEmailHtml();
        yield 'latest jobs' => [new LatestJobsTemplate($html)];
        yield 'spotlight' => [new JobSpotlightTemplate($html)];
        yield 'announcement' => [new AnnouncementTemplate($html)];
        yield 'cv help' => [new CvHelpTemplate($html)];
    }

    #[DataProvider('templates')]
    public function testOutputIsUnchangedByTheSanitiserAndEscapesJobText(NewsletterTemplateInterface $template): void
    {
        $draft = $template->draft([$this->job()], $this->site(), 'https://kaziug.com');

        // Same tags and links after sanitising: nothing the template writes gets stripped.
        $markup = static function (string $html): array {
            preg_match_all('#</?[a-z0-9]+|href="[^"]*"#', $html, $m);

            return $m[0];
        };
        $this->assertSame($markup($draft->bodyHtml), $markup((new HtmlSanitizer())->sanitize($draft->bodyHtml)), 'uses only allowed tags and links');
        $this->assertStringNotContainsString('<img', $draft->bodyHtml);
        $this->assertNotSame('', $draft->subject);
        $this->assertLessThanOrEqual(200, mb_strlen($draft->preheader));
    }

    public function testJobTextIsEscaped(): void
    {
        $draft = (new LatestJobsTemplate(new JobEmailHtml()))->draft([$this->job()], $this->site(), 'https://kaziug.com');

        $this->assertStringContainsString('Nurse &lt;ICU&gt;', $draft->bodyHtml);
        $this->assertStringContainsString('href="https://kaziug.com/jobs/nurse-icu"', $draft->bodyHtml);
        $this->assertSame('New job on KaziUG: Nurse <ICU>', $draft->subject);
    }

    public function testCvTemplateUsesPriceAndWhatsappFromSettings(): void
    {
        $body = (new CvHelpTemplate(new JobEmailHtml()))->draft([], $this->site(), 'https://kaziug.com')->bodyHtml;

        $this->assertStringContainsString('UGX 120,000', $body);
        $this->assertStringContainsString('https://wa.me/256772123456?text=', $body);
    }

    public function testStylerInlinesStylesAndTurnsLoneLinksIntoButtons(): void
    {
        $styled = (new EmailStyler())->style('<h3><a href="https://x.ug/j">Job</a></h3><p>Read <a href="https://x.ug">more</a></p><p><a href="https://x.ug/all">All jobs</a></p>');

        $this->assertStringContainsString('<h3 style="', $styled);
        $this->assertMatchesRegularExpression('#<a href="https://x.ug/j" style="color:\#084f41;text-decoration:none;">#', $styled);
        $this->assertMatchesRegularExpression('#Read <a href="https://x.ug" style="color:\#0c6b58;font-weight:600;">#', $styled);
        $this->assertMatchesRegularExpression('#<a href="https://x.ug/all" style="display:inline-block;background:\#f2b233#', $styled);
        $this->assertSame('', (new EmailStyler())->style('  '));
    }

    private function site(): SiteSettings
    {
        return SiteSettings::fromArray(['whatsapp_number' => '256772123456', 'cv_service_price' => 120000], null);
    }

    private function job(): Job
    {
        $now = new DateTimeImmutable();

        return new Job(
            7, 'Nurse <ICU>', 'nurse-icu', 'Mulago & Co', 'Kampala', false, JobType::FullTime, ExperienceLevel::Mid,
            1, 'Healthcare', 'healthcare', 1_200_000, 1_600_000, 'UGX', 'Care for "patients" <b>daily</b>.',
            ['Run the ward', '<script>x</script>'], ['Diploma in nursing'], ['Triage'], 'https://mulago.ug/apply', null,
            JobStatus::Published, $now, $now->modify('+10 days'), null, $now, $now, 2, null, "Bring copies\nof certificates", null,
        );
    }
}
