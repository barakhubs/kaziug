<?php

declare(strict_types=1);

namespace App\Controllers\Public;

use App\Core\Http\Request;
use App\Domain\Category;
use App\Domain\Job;
use App\Domain\JobSearchCriteria;
use App\Services\Contracts\CategoryServiceInterface;
use App\Services\Contracts\JobSearchServiceInterface;
use App\Services\Contracts\SettingsReaderInterface;

/**
 * Builds the data for the three-column job list (filters, results, detail pane),
 * shared by the home page and category pages.
 */
final class JobListPresenter
{
    public function __construct(
        private readonly JobSearchServiceInterface $search,
        private readonly CategoryServiceInterface $categories,
        private readonly SettingsReaderInterface $settings,
    ) {
    }

    /** @return array<string, mixed> template data for public/jobs/index */
    public function build(Request $request, ?Category $category = null): array
    {
        $criteria = JobSearchCriteria::fromQuery($request->queryAll(), $category?->slug);
        $result = $this->search->search($criteria, $this->settings->site()->jobsPerPage);
        $criteria = $criteria->withPage($result->pagination->page);

        return [
            'criteria' => $criteria,
            'result' => $result,
            'selected' => $this->selected($request, $result->items),
            'categories' => $this->categories->active(),
            'locations' => $this->search->locations(),
            'category' => $category,
            'basePath' => $category === null ? '/' : '/categories/' . $category->slug,
        ];
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
            } catch (\App\Domain\NotFoundException) {
                // fall through to the first result
            }
        }

        return $items[0] ?? null;
    }
}
