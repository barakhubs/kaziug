<?php

declare(strict_types=1);

namespace Tests\Unit\Repositories;

use App\Repositories\FullTextQuery;
use PHPUnit\Framework\TestCase;

final class FullTextQueryTest extends TestCase
{
    public function testIndexableWordsBecomeRequiredStemmedPrefixes(): void
    {
        $query = new FullTextQuery(['nurse', 'developers', 'react']);

        $this->assertSame('+nurs* +develop* +react*', $query->against);
        $this->assertSame([], $query->shortWordPatterns);
    }

    public function testStopwordsAreDroppedAndShortWordsUseTheFallback(): void
    {
        $query = new FullTextQuery(['the', 'hr', 'of', 'qa']);

        $this->assertNull($query->against);
        $this->assertSame(['\\bhr', '\\bqa'], $query->shortWordPatterns);
    }

    public function testStemKeepsAtLeastFourLetters(): void
    {
        $this->assertSame('nurs', FullTextQuery::stem('nursing'));
        $this->assertSame('apis', FullTextQuery::stem('apis'));
        $this->assertSame('sale', FullTextQuery::stem('sales'));
        $this->assertSame('data', FullTextQuery::stem('data'));
    }

    public function testDocumentRepeatsTheTitleSoTitleMatchesRankFirst(): void
    {
        $this->assertSame(
            "Nurse\nNurse\nICU Triage\nMulago\nCare for patients",
            FullTextQuery::document('Nurse', ['ICU', 'Triage'], 'Mulago', 'Care for patients'),
        );
    }
}
