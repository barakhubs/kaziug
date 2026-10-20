<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\HtmlSanitizer;
use App\Services\HtmlToText;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class HtmlSanitizerTest extends TestCase
{
    private HtmlSanitizer $sanitizer;

    protected function setUp(): void
    {
        $this->sanitizer = new HtmlSanitizer();
    }

    public function testKeepsAllowedFormatting(): void
    {
        $html = '<h2>New jobs</h2><p>Hello <strong>Amina</strong>, <em>see</em> <a href="https://kazi.ug/jobs">these</a>.</p>'
            . '<ul><li>One</li><li>Two</li></ul><blockquote>Quote</blockquote><hr><p>Mail <a href="mailto:hi@kazi.ug">us</a><br>Bye</p>';

        $this->assertSame($html, $this->sanitizer->sanitize($html));
    }

    /** @return iterable<string, array{string, string}> */
    public static function attacks(): iterable
    {
        yield 'script tag' => ['<p>Hi</p><script>alert(1)</script>', '<p>Hi</p>'];
        yield 'event handler' => ['<p onclick="alert(1)">Hi</p>', '<p>Hi</p>'];
        yield 'img onerror (unknown tag)' => ['<img src=x onerror=alert(1)>Text', 'Text'];
        yield 'javascript url' => ['<a href="javascript:alert(1)">x</a>', '<a>x</a>'];
        yield 'obfuscated javascript url' => ["<a href=\"java\tscript:alert(1)\">x</a>", '<a>x</a>'];
        yield 'entity-encoded javascript url' => ['<a href="&#106;avascript:alert(1)">x</a>', '<a>x</a>'];
        yield 'data url' => ['<a href="data:text/html,<script>alert(1)</script>">x</a>', '<a>x</a>'];
        yield 'style attribute' => ['<p style="background:url(javascript:alert(1))">x</p>', '<p>x</p>'];
        yield 'iframe with content' => ['<iframe src="https://evil">inner</iframe>ok', 'ok'];
        yield 'svg payload' => ['<svg><script>alert(1)</script></svg>ok', 'ok'];
        yield 'style block' => ['<style>body{display:none}</style><p>x</p>', '<p>x</p>'];
        yield 'comment' => ['<!-- <script>alert(1)</script> --><p>x</p>', '<p>x</p>'];
        yield 'nested unknown tags unwrap' => ['<div><span class="x">Hello</span> <font>world</font></div>', 'Hello world'];
        yield 'form element' => ['<form action="https://evil"><input name="p"></form>ok', 'ok'];
        yield 'unclosed tag' => ['<p>open <strong>bold', '<p>open <strong>bold</strong></p>'];
        yield 'attribute breakout' => ['<a href="https://kazi.ug" onmouseover="x">y</a>', '<a href="https://kazi.ug">y</a>'];
    }

    #[DataProvider('attacks')]
    public function testRemovesDangerousContent(string $input, string $expected): void
    {
        $output = $this->sanitizer->sanitize($input);

        $this->assertSame($expected, $output);
        $this->assertStringNotContainsStringIgnoringCase('javascript', $output);
        $this->assertStringNotContainsStringIgnoringCase('<script', $output);
        $this->assertDoesNotMatchRegularExpression('/\son\w+=/i', $output);
    }

    public function testIsIdempotentAndKeepsUnicode(): void
    {
        $once = $this->sanitizer->sanitize('<p>Kampala – “jobs” ✓ <b>Ebigambo</b></p>');

        $this->assertSame('<p>Kampala – “jobs” ✓ <b>Ebigambo</b></p>', $once);
        $this->assertSame($once, $this->sanitizer->sanitize($once));
    }

    public function testPlainTextConversion(): void
    {
        $text = (new HtmlToText())->convert(
            '<h2>New jobs</h2><p>Hello &amp; welcome, see <a href="https://kazi.ug/jobs?a=1&amp;b=2">the list</a>.</p><ul><li>One</li><li>Two</li></ul><p>Line<br>break</p>',
        );

        $this->assertSame("New jobs\n\nHello & welcome, see the list (https://kazi.ug/jobs?a=1&b=2).\n\n- One\n- Two\n\nLine\nbreak", $text);
    }
}
