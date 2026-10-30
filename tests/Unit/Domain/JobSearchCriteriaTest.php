<?php

declare(strict_types=1);

namespace Tests\Unit\Domain;

use App\Domain\ExperienceLevel;
use App\Domain\JobSearchCriteria;
use App\Domain\JobType;
use App\Domain\Pagination;
use PHPUnit\Framework\TestCase;

final class JobSearchCriteriaTest extends TestCase
{
    public function testParsesValidQueryAndRoundTrips(): void
    {
        $query = [
            'q' => '  react   developer ', 'loc' => 'Kampala', 'category' => 'technology',
            'type' => ['full-time', 'contract', 'full-time'], 'exp' => 'mid', 'posted' => '7',
            'remote' => '1', 'salary_min' => '1,000,000', 'salary_max' => '5000000', 'sort' => 'salary', 'page' => '3',
        ];
        $c = JobSearchCriteria::fromQuery($query);

        $this->assertSame('react developer', $c->q);
        $this->assertSame([JobType::FullTime, JobType::Contract], $c->types);
        $this->assertSame(ExperienceLevel::Mid, $c->experience);
        $this->assertSame(7, $c->postedWithinDays);
        $this->assertTrue($c->remoteOnly);
        $this->assertSame(1_000_000, $c->salaryMin);
        $this->assertSame(3, $c->page);
        $this->assertEquals($c, JobSearchCriteria::fromQuery($c->toQuery()));
    }

    public function testIgnoresGarbage(): void
    {
        $c = JobSearchCriteria::fromQuery([
            'q' => ['array'], 'type' => 'gig', 'exp' => 'guru', 'posted' => '3', 'sort' => 'DROP TABLE',
            'page' => '-4', 'category' => '../etc', 'salary_min' => 'lots',
        ]);

        $this->assertEquals(new JobSearchCriteria(), $c);
        $this->assertSame([], $c->toQuery());
        $this->assertFalse($c->hasFilters());
    }

    public function testSwapsInvertedSalaryRange(): void
    {
        $c = JobSearchCriteria::fromQuery(['salary_min' => '900', 'salary_max' => '100']);

        $this->assertSame([100, 900], [$c->salaryMin, $c->salaryMax]);
    }

    public function testSearchWordsOnlyContainWordCharacters(): void
    {
        $this->assertSame(['front', 'dev'], JobSearchCriteria::fromQuery(['q' => "Front! dev' & | :* +dev"])->searchWords());
        $this->assertSame(['vat', 'paye'], JobSearchCriteria::fromQuery(['q' => 'VAT & PAYE'])->searchWords());
        $this->assertSame([], JobSearchCriteria::fromQuery(['q' => '!!! ***'])->searchWords());
    }

    public function testForcedCategoryWins(): void
    {
        $c = JobSearchCriteria::fromQuery(['category' => 'finance'], 'technology');

        $this->assertSame('technology', $c->categorySlug);
        $this->assertArrayNotHasKey('category', $c->toQuery([], includeCategory: false));
    }

    public function testPaginationWindowAndClamping(): void
    {
        $p = new Pagination(50, 10, 95);

        $this->assertSame(10, $p->page);
        $this->assertSame(90, $p->offset());
        $this->assertSame([1, null, 8, 9, 10], $p->window());
        $this->assertSame([1, 2, 3, 4, 5, null, 10], (new Pagination(3, 10, 95))->window());
    }
}
