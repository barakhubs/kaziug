<?php

declare(strict_types=1);

namespace App\Domain;

/** What opens a marketing popup. */
enum PopupTrigger: string
{
    case Delay = 'delay';
    case Scroll = 'scroll';
    case Exit = 'exit';

    public function label(): string
    {
        return match ($this) {
            self::Delay => 'After a delay',
            self::Scroll => 'After scrolling halfway down the page',
            self::Exit => 'When the visitor moves to leave (desktop; phones use the delay)',
        };
    }

    public function shortLabel(): string
    {
        return match ($this) {
            self::Delay => 'Delay',
            self::Scroll => 'Scroll',
            self::Exit => 'Exit intent',
        };
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
