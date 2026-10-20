<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Domain\Actor;
use App\Domain\DomainException;
use App\Domain\ExperienceLevel;
use App\Domain\JobInput;
use App\Domain\JobStatus;
use App\Domain\JobType;
use App\Domain\NotFoundException;
use App\Services\JobService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tests\Support\Fakes\ImmediateTransactions;
use Tests\Support\Fakes\InMemoryCategoryRepository;
use Tests\Support\Fakes\InMemoryJobRepository;
use Tests\Support\Fakes\SpyAuditService;

final class JobServiceTest extends TestCase
{
    private InMemoryJobRepository $jobs;
    private SpyAuditService $audit;
    private JobService $service;
    private Actor $actor;
    private int $categoryId;

    protected function setUp(): void
    {
        $this->jobs = new InMemoryJobRepository();
        $categories = new InMemoryCategoryRepository();
        $this->categoryId = $categories->create('Technology', 'technology', '', true)->id;
        $this->audit = new SpyAuditService();
        $this->service = new JobService($this->jobs, $categories, new ImmediateTransactions(), $this->audit);
        $this->actor = new Actor(7, 'admin@kazi.ug', '127.0.0.1');
    }

    /** @return iterable<string, array{JobStatus, JobStatus, bool}> */
    public static function transitions(): iterable
    {
        yield 'draft → published' => [JobStatus::Draft, JobStatus::Published, true];
        yield 'draft → closed' => [JobStatus::Draft, JobStatus::Closed, false];
        yield 'draft → archived' => [JobStatus::Draft, JobStatus::Archived, false];
        yield 'published → closed' => [JobStatus::Published, JobStatus::Closed, true];
        yield 'published → draft (unpublish)' => [JobStatus::Published, JobStatus::Draft, true];
        yield 'published → archived' => [JobStatus::Published, JobStatus::Archived, false];
        yield 'closed → archived' => [JobStatus::Closed, JobStatus::Archived, true];
        yield 'closed → published (reopen)' => [JobStatus::Closed, JobStatus::Published, true];
        yield 'closed → draft' => [JobStatus::Closed, JobStatus::Draft, false];
        yield 'archived → published' => [JobStatus::Archived, JobStatus::Published, false];
        yield 'archived → draft' => [JobStatus::Archived, JobStatus::Draft, false];
    }

    #[DataProvider('transitions')]
    public function testTransitionRules(JobStatus $from, JobStatus $to, bool $allowed): void
    {
        $this->assertSame($allowed, $from->canTransitionTo($to));
    }

    public function testCreateDraftRecordsInitialHistoryAndAudits(): void
    {
        $job = $this->service->create($this->input('Data Analyst'), false, $this->actor);

        $this->assertSame(JobStatus::Draft, $job->status);
        $this->assertNull($job->publishedAt);
        $this->assertSame('data-analyst', $job->slug);
        $this->assertSame([['job' => $job->id, 'from' => null, 'to' => 'draft', 'by' => 7]], $this->jobs->history);
        $this->assertSame(['job.created'], $this->audit->actions());
    }

    public function testCreatePublishedSetsPublishedAtAndUniqueSlug(): void
    {
        $first = $this->service->create($this->input('Data Analyst'), true, $this->actor);
        $second = $this->service->create($this->input('Data Analyst'), true, $this->actor);

        $this->assertSame(JobStatus::Published, $first->status);
        $this->assertNotNull($first->publishedAt);
        $this->assertSame('data-analyst-2', $second->slug);
    }

    public function testFullLifecycleRecordsEachChange(): void
    {
        $job = $this->service->create($this->input('Nurse'), false, $this->actor);

        $this->service->changeStatus($job->id, JobStatus::Published, $this->actor);
        $this->service->changeStatus($job->id, JobStatus::Closed, $this->actor);
        $this->service->changeStatus($job->id, JobStatus::Archived, $this->actor);

        $this->assertSame(JobStatus::Archived, $this->service->get($job->id)->status);
        $this->assertSame(
            [[null, 'draft'], ['draft', 'published'], ['published', 'closed'], ['closed', 'archived']],
            array_map(static fn (array $h): array => [$h['from'], $h['to']], $this->jobs->history),
        );
        $this->assertSame(3, count(array_filter($this->audit->actions(), static fn (string $a): bool => $a === 'job.status_changed')));
    }

    public function testRepublishKeepsOriginalPublishDate(): void
    {
        $job = $this->service->create($this->input('Nurse'), true, $this->actor);
        $first = $job->publishedAt;
        $this->service->changeStatus($job->id, JobStatus::Closed, $this->actor);
        $this->service->changeStatus($job->id, JobStatus::Published, $this->actor);

        $this->assertEquals($first, $this->service->get($job->id)->publishedAt);
    }

    public function testIllegalTransitionIsRejectedAndNothingIsRecorded(): void
    {
        $job = $this->service->create($this->input('Nurse'), false, $this->actor);
        $historyBefore = $this->jobs->history;

        $this->expectException(DomainException::class);
        try {
            $this->service->changeStatus($job->id, JobStatus::Archived, $this->actor);
        } finally {
            $this->assertSame($historyBefore, $this->jobs->history);
            $this->assertSame(JobStatus::Draft, $this->service->get($job->id)->status);
        }
    }

    public function testBulkChangeAppliesOnlyAllowedTransitions(): void
    {
        $draft = $this->service->create($this->input('A'), false, $this->actor);
        $published = $this->service->create($this->input('B'), true, $this->actor);
        $alsoDraft = $this->service->create($this->input('C'), false, $this->actor);

        $result = $this->service->bulkChangeStatus([$draft->id, $published->id, $alsoDraft->id, 999], JobStatus::Published, $this->actor);

        $this->assertSame(['changed' => 2, 'skipped' => 2], $result);
        $this->assertSame(JobStatus::Published, $this->service->get($draft->id)->status);
        $this->assertContains('job.bulk_status_changed', $this->audit->actions());
    }

    public function testDuplicateCreatesDraftCopy(): void
    {
        $source = $this->service->create($this->input('Agronomist'), true, $this->actor);

        $copy = $this->service->duplicate($source->id, $this->actor);

        $this->assertNotSame($source->id, $copy->id);
        $this->assertSame(JobStatus::Draft, $copy->status);
        $this->assertSame('agronomist-2', $copy->slug);
        $this->assertSame($source->skills, $copy->skills);
    }

    public function testSoftDeleteHidesJob(): void
    {
        $job = $this->service->create($this->input('Agronomist'), true, $this->actor);
        $this->service->delete($job->id, $this->actor);

        $this->assertNotNull($this->jobs->jobs[$job->id]->deletedAt, 'row is kept');
        $this->expectException(NotFoundException::class);
        $this->service->get($job->id);
    }

    public function testUnknownCategoryIsRejected(): void
    {
        $input = $this->input('X');
        $bad = new JobInput(
            $input->title, $input->company, $input->location, false, $input->jobType, $input->experienceLevel, 999,
            null, null, 'UGX', 'S', [], [], [], null, null, null,
        );

        try {
            $this->service->create($bad, false, $this->actor);
            $this->fail('Expected DomainException');
        } catch (DomainException $e) {
            $this->assertSame('category_id', $e->field);
        }
    }

    private function input(string $title): JobInput
    {
        return new JobInput(
            $title, 'Savanna Tech Labs', 'Kampala', true, JobType::FullTime, ExperienceLevel::Mid, $this->categoryId,
            3_500_000, 5_000_000, 'UGX', 'Build things.', ['Do x'], ['Know y'], ['React', 'CSS'], null, null, null,
        );
    }
}
