<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Http\Request;
use App\Core\Http\Responder;
use App\Core\Http\Response;
use App\Domain\Company;
use App\Domain\DomainException;
use App\Services\Contracts\CompanyServiceInterface;
use App\Validation\CompanyValidator;

/**
 * Companies (employers) and their logos. store() also answers the "+ company" dialog on the job
 * form, which posts with Accept: application/json and gets JSON back instead of a page.
 */
final class CompaniesController
{
    use AdminContext;

    private const LIST_LIMIT = 500;
    private const PICKER_LIMIT = 10;

    public function __construct(
        private readonly CompanyServiceInterface $companies,
        private readonly CompanyValidator $validator,
        private readonly LogoUploadReader $uploads,
        private readonly Responder $responder,
    ) {
    }

    public function index(Request $request): Response
    {
        $q = mb_substr(trim((string) $request->query('q', '')), 0, 100);

        return $this->responder->view('admin/companies/index', [
            'companies' => $this->companies->search($q, self::LIST_LIMIT),
            'q' => $q,
        ]);
    }

    /** JSON for the company picker on the job form. */
    public function search(Request $request): Response
    {
        $q = mb_substr(trim((string) $request->query('q', '')), 0, 100);

        return Response::json([
            'companies' => array_map(static fn (Company $c): array => $c->toPickerArray(), $this->companies->search($q, self::PICKER_LIMIT)),
        ]);
    }

    public function create(Request $request): Response
    {
        return $this->form(null, [], []);
    }

    public function store(Request $request): Response
    {
        $result = $this->validator->validate($request->all());
        $errors = $result->errors;
        $logo = $this->uploads->read($request->file('logo'), $errors);
        if ($logo === null && !isset($errors['logo'])) {
            $errors['logo'] = 'Upload the company logo.';
        }

        if ($errors === []) {
            try {
                $company = $this->companies->create($result->data, (string) $logo, $this->actor($request));
            } catch (DomainException $e) {
                $errors = [$e->field ?? 'name' => $e->getMessage()];
            }
        }

        if ($this->wantsJson($request)) {
            return isset($company) ? Response::json(['company' => $company->toPickerArray()], 201) : Response::json(['errors' => $errors], 422);
        }
        if (!isset($company)) {
            return $this->form(null, $request->all(), $errors, 422);
        }

        return $this->responder->redirectWith($request, '/admin/companies', 'success', 'Company added.');
    }

    public function edit(Request $request): Response
    {
        $company = $this->companies->get($this->id($request));

        return $this->form($company, ['name' => $company->name, 'website' => $company->website ?? ''], []);
    }

    public function update(Request $request): Response
    {
        $company = $this->companies->get($this->id($request));
        $result = $this->validator->validate($request->all());
        $errors = $result->errors;
        $logo = $this->uploads->read($request->file('logo'), $errors);
        if ($logo === null && !$company->hasLogo && !isset($errors['logo'])) {
            $errors['logo'] = 'Upload the company logo.';
        }
        if ($errors !== []) {
            return $this->form($company, $request->all(), $errors, 422);
        }

        try {
            $this->companies->update($company->id, $result->data, $logo, $this->actor($request));
        } catch (DomainException $e) {
            return $this->form($company, $request->all(), [$e->field ?? 'name' => $e->getMessage()], 422);
        }

        return $this->responder->redirectWith($request, '/admin/companies', 'success', 'Company updated.');
    }

    public function delete(Request $request): Response
    {
        try {
            $this->companies->delete($this->id($request), $this->actor($request));
        } catch (DomainException $e) {
            return $this->responder->redirectWith($request, '/admin/companies', 'error', $e->getMessage());
        }

        return $this->responder->redirectWith($request, '/admin/companies', 'success', 'Company deleted.');
    }

    private function wantsJson(Request $request): bool
    {
        return str_contains((string) $request->header('Accept'), 'application/json');
    }

    /**
     * @param array<string, mixed> $values
     * @param array<string, string> $errors
     */
    private function form(?Company $company, array $values, array $errors, int $status = 200): Response
    {
        return $this->responder->view('admin/companies/form', [
            'company' => $company,
            'values' => $values,
            'errors' => $errors,
        ], $status);
    }
}
