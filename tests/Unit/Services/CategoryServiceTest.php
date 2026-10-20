<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Domain\Actor;
use App\Domain\DomainException;
use App\Services\CategoryService;
use PHPUnit\Framework\TestCase;
use Tests\Support\Fakes\InMemoryCategoryRepository;
use Tests\Support\Fakes\SpyAuditService;

final class CategoryServiceTest extends TestCase
{
    private InMemoryCategoryRepository $repo;
    private CategoryService $service;
    private SpyAuditService $audit;

    protected function setUp(): void
    {
        $this->repo = new InMemoryCategoryRepository();
        $this->audit = new SpyAuditService();
        $this->service = new CategoryService($this->repo, $this->audit);
    }

    public function testCreateDerivesUniqueSlugFromName(): void
    {
        $a = $this->service->create(['name' => 'Sales & Marketing', 'slug' => null, 'description' => null, 'is_active' => true], Actor::console());
        $b = $this->service->create(['name' => 'Sales & Marketing', 'slug' => null, 'description' => null, 'is_active' => true], Actor::console());

        $this->assertSame('sales-marketing', $a->slug);
        $this->assertSame('sales-marketing-2', $b->slug);
    }

    public function testExplicitDuplicateSlugIsRejected(): void
    {
        $this->service->create(['name' => 'Tech', 'slug' => 'tech', 'description' => null, 'is_active' => true], Actor::console());

        $this->expectException(DomainException::class);
        $this->service->create(['name' => 'Other', 'slug' => 'tech', 'description' => null, 'is_active' => true], Actor::console());
    }

    public function testDeleteIsBlockedWhileJobsReferenceTheCategory(): void
    {
        $category = $this->service->create(['name' => 'Tech', 'slug' => null, 'description' => null, 'is_active' => true], Actor::console());
        $this->repo->usage[$category->id] = ['live' => 0, 'deleted' => 2];

        try {
            $this->service->delete($category->id, Actor::console());
            $this->fail('Expected DomainException');
        } catch (DomainException $e) {
            $this->assertStringContainsString('used by 2 job(s) (2 of them deleted)', $e->getMessage());
        }
        $this->assertNotNull($this->repo->findById($category->id));

        $this->repo->usage[$category->id] = ['live' => 0, 'deleted' => 0];
        $this->service->delete($category->id, Actor::console());
        $this->assertNull($this->repo->findById($category->id));
        $this->assertContains('category.deleted', $this->audit->actions());
    }

    public function testMoveSwapsWithNeighbourAndIgnoresEdges(): void
    {
        $a = $this->service->create(['name' => 'A', 'slug' => null, 'description' => null, 'is_active' => true], Actor::console());
        $b = $this->service->create(['name' => 'B', 'slug' => null, 'description' => null, 'is_active' => true], Actor::console());
        $c = $this->service->create(['name' => 'C', 'slug' => null, 'description' => null, 'is_active' => true], Actor::console());

        $this->service->move($c->id, 'up', Actor::console());
        $this->service->move($a->id, 'up', Actor::console()); // already first: no-op

        $this->assertSame(['A', 'C', 'B'], array_map(static fn ($x) => $x->name, $this->service->all()));
        $this->assertSame($b->id, $this->service->all()[2]->id);
    }

    public function testEnsureReusesExistingCategoryByName(): void
    {
        $first = $this->service->ensure('Healthcare', Actor::console());
        $second = $this->service->ensure('healthcare', Actor::console());

        $this->assertSame($first->id, $second->id);
    }
}
