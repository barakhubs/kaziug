<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Http\Request;
use App\Core\Http\Responder;
use App\Core\Http\Response;
use App\Domain\DateRange;
use App\Domain\DomainException;
use App\Domain\Job;
use App\Domain\JobStatus;
use App\Domain\TableQuery;
use App\Repositories\Contracts\JobWriteRepositoryInterface;
use App\Services\Contracts\AnalyticsReportServiceInterface;
use App\Services\Contracts\CategoryServiceInterface;
use App\Services\Contracts\CompanyServiceInterface;
use App\Services\Contracts\JobServiceInterface;
use App\Validation\JobValidator;

final class JobsController
{
    use AdminContext;

    private const STATS_DAYS = 30;

    public function __construct(
        private readonly JobServiceInterface $jobs,
        private readonly CategoryServiceInterface $categories,
        private readonly CompanyServiceInterface $companies,
        private readonly JobValidator $validator,
        private readonly Responder $responder,
        private readonly AnalyticsReportServiceInterface $analytics,
        private readonly AnalyticsPresenter $presenter,
    ) {
    }

    public function index(Request $request): Response
    {
        $filters = [
            'q' => mb_substr(trim((string) $request->query('q', '')), 0, 100),
            'status' => in_array($request->query('status'), JobStatus::values(), true) ? (string) $request->query('status') : '',
            'category' => max(0, (int) $request->query('category', 0)),
            'urgent' => $request->query('urgent') === '1' ? '1' : '',
        ];
        $table = TableQuery::fromQuery($request->queryAll(), JobWriteRepositoryInterface::SORTS, 'updated');
        $result = $this->jobs->search([
            'q' => $filters['q'], 'status' => $filters['status'], 'category_id' => $filters['category'], 'urgent' => $filters['urgent'] === '1',
        ], $table);

        return $this->responder->view('admin/jobs/index', [
            'result' => $result,
            'table' => $table->withPage($result->pagination->page),
            'filters' => $filters,
            'categories' => $this->categories->all(),
            'statusCounts' => $this->jobs->countByStatus(),
        ]);
    }

    /** Read-only details: everything about the job, its status controls and history. */
    public function show(Request $request): Response
    {
        $job = $this->jobs->get($this->id($request));

        $stats = $this->analytics->forJob($job->id, DateRange::lastDays(self::STATS_DAYS));

        return $this->responder->view('admin/jobs/show', [
            'job' => $job,
            'history' => $this->jobs->history($job->id),
            'stats' => $stats['totals'],
            'statsDays' => self::STATS_DAYS,
            'statsChart' => $this->presenter->chart('Views per day', $stats['series'], 'pageviews', 150, 300),
        ]);
    }

    public function create(Request $request): Response
    {
        return $this->form(null, self::defaults(), []);
    }

    public function store(Request $request): Response
    {
        $values = $this->formInput($request);
        [$input, $errors] = $this->validator->validate($values);
        if ($input === null) {
            return $this->form(null, $values, $errors, 422);
        }

        try {
            $job = $this->jobs->create($input, $request->input('intent') === 'publish', $this->actor($request));
        } catch (DomainException $e) {
            return $this->form(null, $values, [$e->field ?? 'title' => $e->getMessage()], 422);
        }

        $message = $job->status === JobStatus::Published ? 'Job created and published.' : 'Job saved as a draft.';

        return $this->responder->redirectWith($request, "/admin/jobs/{$job->id}", 'success', $message);
    }

    public function edit(Request $request): Response
    {
        $job = $this->jobs->get($this->id($request));

        return $this->form($job, self::valuesFrom($job), []);
    }

    public function update(Request $request): Response
    {
        $job = $this->jobs->get($this->id($request));
        $values = $this->formInput($request);
        [$input, $errors] = $this->validator->validate($values, $job->expiresAt);
        if ($input === null) {
            return $this->form($job, $values, $errors, 422);
        }

        try {
            $this->jobs->update($job->id, $input, $this->actor($request));
        } catch (DomainException $e) {
            return $this->form($job, $values, [$e->field ?? 'title' => $e->getMessage()], 422);
        }

        return $this->responder->redirectWith($request, "/admin/jobs/{$job->id}", 'success', 'Job updated.');
    }

    public function changeStatus(Request $request): Response
    {
        $id = $this->id($request);
        $to = JobStatus::tryFrom((string) $request->input('to'));
        $back = $this->returnTo($request, "/admin/jobs/{$id}");
        if ($to === null) {
            return $this->responder->redirectWith($request, $back, 'error', 'Choose a status.');
        }

        try {
            $this->jobs->changeStatus($id, $to, $this->actor($request));
        } catch (DomainException $e) {
            return $this->responder->redirectWith($request, $back, 'error', $e->getMessage());
        }

        return $this->responder->redirectWith($request, $back, 'success', "Job moved to {$to->value}.");
    }

    public function bulkStatus(Request $request): Response
    {
        $to = JobStatus::tryFrom((string) $request->input('to'));
        $ids = $request->input('ids');
        $back = $this->returnTo($request, '/admin/jobs');
        if ($to === null || !is_array($ids) || $ids === []) {
            return $this->responder->redirectWith($request, $back, 'error', 'Select at least one job and a status.');
        }

        $result = $this->jobs->bulkChangeStatus(array_values(array_filter($ids, 'is_numeric')), $to, $this->actor($request));
        $message = "{$result['changed']} job(s) moved to {$to->value}.";
        if ($result['skipped'] > 0) {
            $message .= " {$result['skipped']} skipped because that change isn't allowed from their current status.";
        }

        return $this->responder->redirectWith($request, $back, $result['changed'] > 0 ? 'success' : 'error', $message);
    }

    public function duplicate(Request $request): Response
    {
        $copy = $this->jobs->duplicate($this->id($request), $this->actor($request));

        return $this->responder->redirectWith($request, "/admin/jobs/{$copy->id}/edit", 'success', 'Copy created as a draft. Edit it below.');
    }

    public function delete(Request $request): Response
    {
        $this->jobs->delete($this->id($request), $this->actor($request));

        return $this->responder->redirectWith($request, $this->returnTo($request, '/admin/jobs'), 'success', 'Job deleted.');
    }

    /**
     * @param array<string, mixed> $values
     * @param array<string, string> $errors
     */
    private function form(?Job $job, array $values, array $errors, int $status = 200): Response
    {
        return $this->responder->view('admin/jobs/form', [
            'job' => $job,
            'values' => $values,
            'errors' => $errors,
            'categories' => $this->categories->all(),
        ], $status);
    }

    /**
     * The posted form. The company picker sends company_id; without JavaScript only the typed
     * company_name arrives, so an exact (case-insensitive) name match stands in for the id.
     * @return array<string, mixed>
     */
    private function formInput(Request $request): array
    {
        $values = $request->all();
        $name = trim((string) ($values['company_name'] ?? ''));
        if (($values['company_id'] ?? '') === '' && $name !== '') {
            $values['company_id'] = (string) ($this->companies->findByName($name)?->id ?? '');
        }

        return $values;
    }

    /** Where to go after an action: a same-site /admin/jobs URL from the form, or $default. */
    private function returnTo(Request $request, string $default): string
    {
        $value = $request->input('return_to');

        return is_string($value) && preg_match('#^/admin/jobs(/[\w/-]*)?(\?[^\s\\\\]*)?$#', $value) === 1 ? $value : $default;
    }

    /** @return array<string, mixed> */
    private static function defaults(): array
    {
        return ['job_type' => 'full-time', 'experience_level' => 'entry', 'currency' => 'UGX', 'apply_method' => 'link', 'positions' => '1'];
    }

    /** @return array<string, mixed> */
    private static function valuesFrom(Job $job): array
    {
        return [
            'title' => $job->title,
            'company_id' => (string) $job->companyId,
            'company_name' => $job->company,
            'location' => $job->location,
            'is_remote' => $job->isRemote ? '1' : '',
            'is_urgent' => $job->isUrgent ? '1' : '',
            'job_type' => $job->jobType->value,
            'experience_level' => $job->experienceLevel->value,
            'category_id' => (string) $job->categoryId,
            'salary_min' => $job->salaryMin === null ? '' : (string) $job->salaryMin,
            'salary_max' => $job->salaryMax === null ? '' : (string) $job->salaryMax,
            'currency' => $job->currency,
            'summary' => $job->summary,
            'responsibilities' => implode("\n", $job->responsibilities),
            'requirements' => implode("\n", $job->requirements),
            'skills' => implode(', ', $job->skills),
            'apply_method' => JobValidator::applyMethodFor($job->applyUrl, $job->applyEmail),
            'apply_url' => $job->applyUrl ?? '',
            'apply_email' => $job->applyEmail ?? '',
            'expires_at' => $job->expiresAt?->format('Y-m-d') ?? '',
            'positions' => (string) $job->positions,
            'salary_scale' => $job->salaryScale ?? '',
            'application_instructions' => $job->applicationInstructions ?? '',
            'reports_to' => $job->reportsTo ?? '',
        ];
    }
}
