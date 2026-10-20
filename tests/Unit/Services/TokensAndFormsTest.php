<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Core\AppKey;
use App\Services\CsvWriter;
use App\Services\FormTimer;
use App\Services\Import\CsvSubscriberParser;
use App\Services\TokenService;
use PHPUnit\Framework\TestCase;

final class TokensAndFormsTest extends TestCase
{
    public function testTokensAreRandomUrlSafeAndHashedOneWay(): void
    {
        $tokens = new TokenService();
        $a = $tokens->generate();
        $b = $tokens->generate();

        $this->assertNotSame($a, $b);
        $this->assertTrue($tokens->looksValid($a));
        $this->assertMatchesRegularExpression('/^[A-Za-z0-9_-]{43}$/', $a);
        $this->assertSame(64, strlen($tokens->hash($a)));
        $this->assertNotSame($a, $tokens->hash($a));
        $this->assertSame($tokens->hash($a), $tokens->hash($a));
        $this->assertFalse($tokens->looksValid('short'));
        $this->assertFalse($tokens->looksValid(str_repeat('a', 42) . '/'));
    }

    public function testFormTimerRejectsFastForgedAndStaleSubmissions(): void
    {
        $timer = new FormTimer(new AppKey('base64:' . base64_encode(str_repeat('f', 32))));
        $now = 1_800_000_000;
        $stamp = $timer->issue($now);

        $this->assertFalse($timer->isHuman($stamp, $now + 1), 'too fast');
        $this->assertTrue($timer->isHuman($stamp, $now + FormTimer::MIN_SECONDS));
        $this->assertFalse($timer->isHuman($stamp, $now + FormTimer::MAX_AGE_SECONDS + 1), 'stale');
        $this->assertFalse($timer->isHuman(($now - 60) . '.' . explode('.', $stamp)[1], $now), 'time changed, signature not');
        $this->assertFalse($timer->isHuman(null));
        $this->assertFalse($timer->isHuman('garbage'));
    }

    public function testCsvParserHandlesHeadersDelimitersInvalidAndDuplicates(): void
    {
        $parser = new CsvSubscriberParser();

        $withHeader = $parser->parse("\xEF\xBB\xBFName,Email\nAmina,AMINA@example.com\nBad,not-an-email\nAmina again,amina@example.com\n\n,joe@example.org\n");
        $this->assertSame([['email' => 'amina@example.com', 'name' => 'Amina'], ['email' => 'joe@example.org', 'name' => null]], $withHeader['rows']);
        $this->assertSame([['line' => 3, 'reason' => 'Not a valid email address.']], $withHeader['invalid']);
        $this->assertSame(1, $withHeader['duplicates_in_file']);

        $noHeader = $parser->parse("a@example.com;Alice\nb@example.com\n");
        $this->assertSame([['email' => 'a@example.com', 'name' => 'Alice'], ['email' => 'b@example.com', 'name' => null]], $noHeader['rows']);
    }

    public function testCsvWriterNeutralisesFormulas(): void
    {
        $this->assertSame("'=HYPERLINK(\"x\")", CsvWriter::cell('=HYPERLINK("x")'));
        $this->assertSame("'+1", CsvWriter::cell('+1'));
        $this->assertSame("'@SUM(A1)", CsvWriter::cell('@SUM(A1)'));
        $this->assertSame('plain@example.com', CsvWriter::cell('plain@example.com'));
        $this->assertSame('', CsvWriter::cell(null));
    }
}
