<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use App\Core\Database\PgArray;
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

    public function testPgArrayRoundTripsAwkwardValues(): void
    {
        $values = ['React', 'VAT & PAYE', 'say "hi"', 'back\\slash', 'a,b', ''];

        $this->assertSame($values, PgArray::toList(PgArray::fromList($values)));
        $this->assertSame([], PgArray::toList('{}'));
        $this->assertSame(['plain', 'words'], PgArray::toList('{plain,words}'));
    }
}
