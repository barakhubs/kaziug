<?php

declare(strict_types=1);

namespace App\Controllers\Public;

use App\Core\Http\Request;
use App\Domain\Category;
use App\Domain\Job;
use App\Domain\JobSearchCriteria;
use App\Domain\NotFoundException;
use App\Domain\PageContext;
use App\Domain\PageType;
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
        $selected = $this->selected($request, $result->items);
        // Before the page number is clamped: asking for page 2 is paging through a search, not a new one.
        $request->setAttribute(PageContext::ATTRIBUTE, $this->pageContext($request, $criteria, $result->pagination->total, $selected, $category, $city));
        $criteria = $criteria->withPage($result->pagination->page);

        return [
            'criteria' => $criteria,
            'result' => $result,
            'selected' => $selected,
            'categories' => $this->categories->active(),
            'locations' => $this->search->locations(),
            'cities' => $this->search->cities(),
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

    /**
     * For analytics: which list this is, the job opened in the detail pane (only when the visitor
     * picked it, not the default first result), and the search, counted on its first page only.
     * @param array{name: string, slug: string, count: int}|null $city
     */
    private function pageContext(Request $request, JobSearchCriteria $criteria, int $total, ?Job $selected, ?Category $category, ?array $city): PageContext
    {
        $picked = is_string($request->query('job')) && $selected !== null ? $selected : null;
        $filters = array_keys(array_filter([
            'location' => $city === null && $criteria->location !== '',
            'category' => $category === null && $criteria->categorySlug !== null,
            'type' => $criteria->types !== [],
            'experience' => $criteria->experience !== null,
            'posted' => $criteria->postedWithinDays !== null,
            'remote' => $criteria->remoteOnly,
            'salary' => $criteria->salaryMin !== null || $criteria->salaryMax !== null,
        ]));
        $isSearch = ($criteria->q !== '' || $filters !== []) && $criteria->page === 1;

        return new PageContext(
            match (true) {
                $category !== null => PageType::Category,
                $city !== null => PageType::City,
                default => PageType::Home,
            },
            $picked?->id,
            $category?->id ?? $picked?->categoryId,
            $isSearch && $criteria->q !== '' ? mb_strtolower($criteria->q) : null,
            $isSearch ? $filters : [],
            $isSearch ? $total : null,
        );
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
