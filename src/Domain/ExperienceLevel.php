<?php

declare(strict_types=1);

namespace App\Domain;

enum ExperienceLevel: string
{
    case Entry = 'entry';
    case Mid = 'mid';
    case Senior = 'senior';

    public function label(): string
    {
        return ucfirst($this->value);
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
