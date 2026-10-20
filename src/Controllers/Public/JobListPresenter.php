<?php

declare(strict_types=1);

namespace App\Controllers\Public;

use App\Core\Http\Request;
use App\Domain\Category;
use App\Domain\Job;
use App\Domain\JobSearchCriteria;
use App\Domain\NotFoundException;
use App\Services\Contracts\CategoryServiceInterface;
use App\Services\Contracts\JobSearchServiceInterface;
use App\Services\Contracts\SettingsReaderInterface;
use App\Services\Seo\StructuredData;

/**
 * Builds the data for the three-column job list (filters, results, detail pane), shared by the
 * home page, category pages and city pages.
 */
final class JobListPresenter
{
    public function __construct(
        private readonly JobSearchServiceInterface $search,
        private readonly CategoryServiceInterface $categories,
        private readonly SettingsReaderInterface $settings,
        private readonly StructuredData $structuredData,
    ) {
    }

    /**
     * @param array{name: string, slug: string, count: int}|null $city
     * @return array<string, mixed> template data for public/jobs/index
     */
    public function build(Request $request, ?Category $category = null, ?array $city = null): array
    {
        $criteria = JobSearchCriteria::fromQuery($request->queryAll(), $category?->slug, $city['name'] ?? null);
        $result = $this->search->search($criteria, $this->settings->site()->jobsPerPage);
        $criteria = $criteria->withPage($result->pagination->page);
        $cities = $this->search->cities();

        return [
            'criteria' => $criteria,
            'result' => $result,
            'selected' => $this->selected($request, $result->items),
            'categories' => $this->categories->active(),
            'locations' => $this->search->locations(),
            'cities' => $cities,
            'category' => $category,
            'city' => $city,
            'includeCategory' => $category === null,
            'includeLocation' => $city === null,
            'basePath' => match (true) {
                $category !== null => '/categories/' . $category->slug,
                $city !== null => '/jobs-in/' . $city['slug'],
                default => '/',
            },
            'jsonLd' => $this->jsonLd($category, $city),
        ];
    }

    /** @return array{name: string, slug: string, count: int} */
    public function city(string $slug): array
    {
        return $this->search->findCity($slug) ?? throw new NotFoundException('There are no open jobs in this city right now.');
    }

    /**
     * @param array{name: string, slug: string, count: int}|null $city
     * @return list<string>
     */
    private function jsonLd(?Category $category, ?array $city): array
    {
        if ($category !== null) {
            return [$this->structuredData->breadcrumbs([['Jobs', '/'], [$category->name . ' jobs', '/categories/' . $category->slug]])];
        }
        if ($city !== null) {
            return [$this->structuredData->breadcrumbs([['Jobs', '/'], ['Jobs in ' . $city['name'], '/jobs-in/' . $city['slug']]])];
        }

        return [$this->structuredData->organization(), $this->structuredData->website()];
    }

    /** The job in the detail pane: ?job=<slug> if visible, otherwise the first result. */
    private function selected(Request $request, array $items): ?Job
    {
        $slug = $request->query('job');
        if (is_string($slug) && $slug !== '') {
            foreach ($items as $job) {
                if ($job->slug === $slug) {
                    return $job;
                }
            }
            try {
                return $this->search->findBySlug($slug);
            } catch (NotFoundException) {
                // fall through to the first result
            }
        }

        return $items[0] ?? null;
    }
}
