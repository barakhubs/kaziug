<?php

declare(strict_types=1);

namespace App\Controllers\Public;

use App\Core\Http\Request;
use App\Core\Http\Responder;
use App\Core\Http\Response;
use App\Domain\NotFoundException;
use App\Services\Contracts\CategoryServiceInterface;

final class CategoryController
{
    public function __construct(
        private readonly CategoryServiceInterface $categories,
        private readonly JobListPresenter $presenter,
        private readonly Responder $responder,
    ) {
    }

    public function show(Request $request): Response
    {
        $category = $this->categories->findActiveBySlug((string) $request->attribute('slug'))
            ?? throw new NotFoundException('Category not found.');

        return $this->responder->view('public/jobs/index', $this->presenter->build($request, $category));
    }
}
