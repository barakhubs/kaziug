<?php

declare(strict_types=1);

namespace App\Services;

use App\Domain\Job;
use App\Domain\JobSearchCriteria;
use App\Domain\NotFoundException;
use App\Domain\Paginated;
use App\Domain\Pagination;
use App\Domain\Slug;
use App\Repositories\Contracts\JobReadRepositoryInterface;
use App\Services\Contracts\JobSearchServiceInterface;

final class JobSearchService implements JobSearchServiceInterface
{
    private const MAX_SAVED = 100;

    public function __construct(private readonly JobReadRepositoryInterface $jobs)
    {
    }

    public function search(JobSearchCriteria $criteria, int $perPage): Paginated
    {
        $result = $this->jobs->searchPublished($criteria, $perPage, ($criteria->page - 1) * $perPage);
        $pagination = new Pagination($criteria->page, $perPage, $result['total']);
        if ($pagination->page !== $criteria->page) {
            $result = $this->jobs->searchPublished($criteria, $perPage, $pagination->offset());
        }

        return new Paginated($result['items'], $pagination);
    }

    public function findBySlug(string $slug): Job
    {
        return $this->jobs->findPublishedBySlug($slug) ?? throw new NotFoundException('This job is no longer available.');
    }

    public function savedJobs(array $ids): array
    {
        return $this->jobs->findPublishedByIds(array_slice($ids, 0, self::MAX_SAVED));
    }

    public function locations(): array
    {
        return $this->jobs->publishedLocations();
    }

    public function cities(): array
    {
        $cities = [];
        foreach ($this->jobs->locationCounts() as $row) {
            $slug = Slug::from($row['location']);
            // Several spellings of one city ("Kampala", "kampala ") share a page.
            if (isset($cities[$slug])) {
                $cities[$slug]['count'] += $row['count'];
                continue;
            }
            $cities[$slug] = ['name' => $row['location'], 'slug' => $slug, 'count' => $row['count']];
        }

        return array_values($cities);
    }

    public function findCity(string $slug): ?array
    {
        foreach ($this->cities() as $city) {
            if ($city['slug'] === $slug) {
                return $city;
            }
        }

        return null;
    }

    public function sitemapEntries(): array
    {
        return $this->jobs->sitemapEntries();
    }

    public function related(Job $job, int $limit): array
    {
        return $this->jobs->related($job->id, $job->categoryId, $job->location, $limit);
    }

    public function latest(int $limit): array
    {
        return $this->jobs->searchPublished(new JobSearchCriteria(sort: 'date'), $limit, 0)['items'];
    }
}
