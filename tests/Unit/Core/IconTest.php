<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use App\Core\Icon;
use App\Domain\SocialIcon;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class IconTest extends TestCase
{
    public function testEverySocialIconAndTheLayoutIconsExist(): void
    {
        $names = [...array_map(static fn (SocialIcon $i): string => $i->iconName(), SocialIcon::cases()), 'solid/sliders'];
        foreach ($names as $name) {
            $this->assertMatchesRegularExpression(
                '#^<svg class="icon" width="[\d.]+em" height="1em" viewBox="0 0 \d+ \d+" aria-hidden="true" focusable="false"><path fill="currentColor" d="[^"<>]+"/></svg>$#',
                Icon::svg($name),
                $name,
            );
        }
    }

    public function testKeepsEachGlyphsProportions(): void
    {
        $this->assertStringContainsString('width="0.875em"', Icon::svg('brands/whatsapp'), '448 × 512');
    }

    public function testRejectsNamesOutsideTheIconFolder(): void
    {
        $this->expectException(RuntimeException::class);
        Icon::svg('../../config/routes');
    }
}
