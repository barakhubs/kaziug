<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use App\Console\Commands\ConfigCheckCommand;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ConfigCheckUrlTest extends TestCase
{
    /** @return iterable<string, array{string, bool}> */
    public static function urls(): iterable
    {
        yield 'public https' => ['https://kaziug.com', true];
        yield 'http' => ['http://kaziug.com', false];
        yield 'localhost' => ['https://localhost:8000', false];
        yield 'loopback' => ['https://127.0.0.1', false];
        yield 'local dev domain' => ['https://ugjobs.test', false];
        yield 'trailing slash' => ['https://kaziug.com/', false];
    }

    #[DataProvider('urls')]
    public function testProductionUrlMustBeThePublicHttpsAddress(string $url, bool $passes): void
    {
        $this->assertSame($passes, !in_array(false, ConfigCheckCommand::productionUrlChecks($url), true));
    }
}
