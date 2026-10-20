<?php

declare(strict_types=1);

namespace App\Domain;

final class SocialProfile
{
    public function __construct(
        public readonly int $id,
        public readonly string $platform,
        public readonly string $label,
        public readonly string $url,
        public readonly SocialIcon $icon,
        public readonly int $sortOrder,
        public readonly bool $isVisible,
    ) {
    }
}
