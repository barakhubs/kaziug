<?php

declare(strict_types=1);

namespace App\Domain;

/** How a visitor chose to apply for a job: the employer's link or email. */
enum ApplyChannel: string
{
    case Link = 'link';
    case Email = 'email';

    public function label(): string
    {
        return match ($this) {
            self::Link => 'Application link',
            self::Email => 'Email',
        };
    }
}
