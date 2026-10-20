<?php

declare(strict_types=1);

namespace Tests\Unit\Validation;

use App\Validation\Rules\DateTimeRule;
use App\Validation\Rules\IntegerRule;
use App\Validation\Validator;
use PHPUnit\Framework\TestCase;

final class ValidatorTest extends TestCase
{
    private Validator $v;

    protected function setUp(): void
    {
        $this->v = new Validator();
    }

    public function testTrimsAndValidatesRequiredFields(): void
    {
        $result = $this->v->validate(['name' => '  Kampala  ', 'email' => 'bad'], ['name' => 'required|max:20', 'email' => 'required|email']);

        $this->assertSame('Kampala', $result->data['name']);
        $this->assertSame(['email' => 'Enter a valid email address.'], $result->errors);
    }

    public function testOptionalEmptyFieldsBecomeNullAndSkipRules(): void
    {
        $result = $this->v->validate(['url' => '  '], ['url' => 'url', 'missing' => 'integer']);

        $this->assertTrue($result->passes());
        $this->assertNull($result->data['url']);
        $this->assertNull($result->data['missing']);
    }

    public function testUrlRuleOnlyAllowsHttp(): void
    {
        $rules = ['u' => 'required|url'];

        $this->assertTrue($this->v->validate(['u' => 'https://kazi.ug/jobs'], $rules)->passes());
        $this->assertTrue($this->v->validate(['u' => 'javascript:alert(1)'], $rules)->fails());
        $this->assertTrue($this->v->validate(['u' => 'data:text/html,x'], $rules)->fails());
        $this->assertTrue($this->v->validate(['u' => '//evil.example'], $rules)->fails());
    }

    public function testIntegerBoundsAndThousandsSeparators(): void
    {
        $rules = ['n' => 'integer:0,100'];

        $this->assertTrue($this->v->validate(['n' => '42'], $rules)->passes());
        $this->assertTrue($this->v->validate(['n' => '101'], $rules)->fails());
        $this->assertTrue($this->v->validate(['n' => '-1'], $rules)->fails());
        $this->assertTrue($this->v->validate(['n' => '4.5'], $rules)->fails());
        $this->assertTrue($this->v->validate(['salary' => '3,500,000'], ['salary' => 'integer:0'])->passes());
        $this->assertSame(3500000, IntegerRule::toInt('3,500,000'));
    }

    public function testInSameBooleanAndArraysAreRejectedWhereStringsExpected(): void
    {
        $this->assertTrue($this->v->validate(['t' => 'contract'], ['t' => 'required|in:full-time,contract'])->passes());
        $this->assertTrue($this->v->validate(['t' => 'gig'], ['t' => 'required|in:full-time,contract'])->fails());
        $this->assertTrue($this->v->validate(['a' => 'x', 'b' => 'y'], ['b' => 'required|same:a'])->fails());
        $this->assertTrue($this->v->validate(['t' => ['x']], ['t' => 'required|max:5'])->fails());

        $bool = $this->v->validate(['on' => 'on'], ['on' => 'boolean', 'off' => 'boolean']);
        $this->assertSame(['on' => true, 'off' => false], $bool->data);
    }

    public function testDateParsing(): void
    {
        $this->assertSame('2026-11-01 09:30', DateTimeRule::parse('2026-11-01T09:30')?->format('Y-m-d H:i'));
        $this->assertSame('2026-11-01 00:00', DateTimeRule::parse('2026-11-01')?->format('Y-m-d H:i'));
        $this->assertNull(DateTimeRule::parse('2026-02-31'));
        $this->assertTrue($this->v->validate(['d' => 'tomorrow'], ['d' => 'datetime'])->fails());
    }
}
