<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Http\Request;
use App\Core\Http\Responder;
use App\Core\Http\Response;
use App\Domain\Category;
use App\Domain\DomainException;
use App\Services\Contracts\CategoryServiceInterface;
use App\Validation\CategoryValidator;

final class CategoriesController
{
    use AdminContext;

    public function __construct(
        private readonly CategoryServiceInterface $categories,
        private readonly CategoryValidator $validator,
        private readonly Responder $responder,
    ) {
    }

    public function index(Request $request): Response
    {
        return $this->responder->view('admin/categories/index', ['categories' => $this->categories->all()]);
    }

    public function create(Request $request): Response
    {
        return $this->form(null, ['is_active' => '1'], []);
    }

    public function store(Request $request): Response
    {
        $result = $this->validator->validate($request->all());
        if ($result->fails()) {
            return $this->form(null, $request->all(), $result->errors, 422);
        }

        try {
            $this->categories->create($result->data, $this->actor($request));
        } catch (DomainException $e) {
            return $this->form(null, $request->all(), [$e->field ?? 'name' => $e->getMessage()], 422);
        }

        return $this->responder->redirectWith($request, '/admin/categories', 'success', 'Category created.');
    }

    public function edit(Request $request): Response
    {
        $category = $this->categories->get($this->id($request));

        return $this->form($category, [
            'name' => $category->name,
            'slug' => $category->slug,
            'description' => $category->description,
        ], []);
    }

    public function update(Request $request): Response
    {
        $category = $this->categories->get($this->id($request));
        $result = $this->validator->validate($request->all());
        if ($result->fails()) {
            return $this->form($category, $request->all(), $result->errors, 422);
        }

        try {
            $this->categories->update($category->id, $result->data, $this->actor($request));
        } catch (DomainException $e) {
            return $this->form($category, $request->all(), [$e->field ?? 'name' => $e->getMessage()], 422);
        }

        return $this->responder->redirectWith($request, '/admin/categories', 'success', 'Category updated.');
    }

    public function toggle(Request $request): Response
    {
        $active = $request->input('active') === '1';
        $this->categories->setActive($this->id($request), $active, $this->actor($request));

        return $this->responder->redirectWith($request, '/admin/categories', 'success', $active ? 'Category activated.' : 'Category deactivated.');
    }

    public function move(Request $request): Response
    {
        $direction = $request->input('direction') === 'up' ? 'up' : 'down';
        $this->categories->move($this->id($request), $direction, $this->actor($request));

        return $this->responder->redirect('/admin/categories');
    }

    public function delete(Request $request): Response
    {
        try {
            $this->categories->delete($this->id($request), $this->actor($request));
        } catch (DomainException $e) {
            return $this->responder->redirectWith($request, '/admin/categories', 'error', $e->getMessage());
        }

        return $this->responder->redirectWith($request, '/admin/categories', 'success', 'Category deleted.');
    }

    /**
     * @param array<string, mixed> $values
     * @param array<string, string> $errors
     */
    private function form(?Category $category, array $values, array $errors, int $status = 200): Response
    {
        return $this->responder->view('admin/categories/form', [
            'category' => $category,
            'values' => $values,
            'errors' => $errors,
        ], $status);
    }
}
