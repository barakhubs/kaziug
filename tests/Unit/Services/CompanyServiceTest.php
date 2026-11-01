<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Domain\Actor;
use App\Domain\DomainException;
use App\Services\CompanyService;
use App\Services\ImageInspector;
use PHPUnit\Framework\TestCase;
use Tests\Support\Fakes\ImmediateTransactions;
use Tests\Support\Fakes\InMemoryCompanyRepository;
use Tests\Support\Fakes\InMemoryJobRepository;
use Tests\Support\Fakes\SpyAuditService;

final class CompanyServiceTest extends TestCase
{
    private InMemoryCompanyRepository $companies;
    private InMemoryJobRepository $jobs;
    private CompanyService $service;
    private Actor $actor;

    protected function setUp(): void
    {
        $this->companies = new InMemoryCompanyRepository();
        $this->jobs = new InMemoryJobRepository();
        $this->service = new CompanyService($this->companies, $this->jobs, new ImageInspector(), new ImmediateTransactions(), new SpyAuditService());
        $this->actor = new Actor(7, 'admin@kazi.ug', '127.0.0.1');
    }

    private function png(): string
    {
        $image = imagecreatetruecolor(30, 30);
        ob_start();
        imagepng($image);

        return (string) ob_get_clean();
    }

    public function testCreateNeedsALogo(): void
    {
        try {
            $this->service->create(['name' => 'Acme', 'website' => null], '', $this->actor);
            $this->fail('Expected DomainException');
        } catch (DomainException $e) {
            $this->assertSame('logo', $e->field);
        }

        $company = $this->service->create(['name' => 'Acme', 'website' => null], $this->png(), $this->actor);
        $this->assertTrue($company->hasLogo);
        $this->assertSame(1, $company->logoVersion);
    }

    public function testOnlyARenameRebuildsTheJobsSearchText(): void
    {
        $company = $this->service->create(['name' => 'Acme', 'website' => null], $this->png(), $this->actor);

        $this->service->update($company->id, ['name' => 'Acme', 'website' => 'https://acme.example'], null, $this->actor);
        $this->assertSame([], $this->jobs->reindexed);

        $this->service->update($company->id, ['name' => 'Acme Uganda', 'website' => null], null, $this->actor);
        $this->assertSame([$company->id], $this->jobs->reindexed);
        $this->assertSame(1, $this->service->get($company->id)->logoVersion, 'logo kept when none is uploaded');
    }

    public function testDeleteIsBlockedWhileJobsUseTheCompany(): void
    {
        $company = $this->service->create(['name' => 'Acme', 'website' => null], $this->png(), $this->actor);
        $this->companies->jobCounts[$company->id] = 2;

        $this->expectException(DomainException::class);
        $this->service->delete($company->id, $this->actor);
    }

    public function testImportsReuseCompaniesByNameAndCreateMissingOnesWithoutALogo(): void
    {
        $first = $this->service->idForImportedName('Savanna Labs');
        $this->assertSame($first, $this->service->idForImportedName('SAVANNA LABS'));
        $this->assertFalse($this->service->get($first)->hasLogo);
    }
}
