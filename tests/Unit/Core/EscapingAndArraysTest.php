<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use App\Core\Database\DbTime;
use App\Core\Database\Migrator;
use PHPUnit\Framework\TestCase;

final class EscapingAndArraysTest extends TestCase
{
    public function testEscapeHelperNeutralisesHtmlAndAttributeBreakouts(): void
    {
        $this->assertSame('&lt;script&gt;alert(1)&lt;/script&gt;', e('<script>alert(1)</script>'));
        $this->assertSame('&quot; onmouseover=&quot;x&quot; &apos;', e('" onmouseover="x" \''));
        $this->assertSame('', e(null));
        $this->assertSame('42', e(42));
    }

    public function testDbTimeStoresUtcAndReadsBackInAppTimezone(): void
    {
        $kampala = new \DateTimeImmutable('2026-03-01 09:30:00', new \DateTimeZone('Africa/Kampala'));

        $this->assertSame('2026-03-01 06:30:00.000000', DbTime::toDb($kampala));
        $this->assertSame($kampala->getTimestamp(), DbTime::fromDb(DbTime::toDb($kampala))?->getTimestamp());
        $this->assertNull(DbTime::fromDb(null));
        $this->assertNull(DbTime::toDb(null));
    }

    public function testMigrationSplitterIgnoresSemicolonsInQuotesAndComments(): void
    {
        $sql = "-- note; not a statement\nCREATE TABLE a (x TEXT DEFAULT 'a;b');\nINSERT INTO a VALUES ('it\\'s; fine');\n\n";

        $this->assertSame(
            ["CREATE TABLE a (x TEXT DEFAULT 'a;b')", "INSERT INTO a VALUES ('it\\'s; fine')"],
            Migrator::statements($sql),
        );
    }
}
