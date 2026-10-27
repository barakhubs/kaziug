<?php

declare(strict_types=1);

namespace App\Services;

/** Mobile, tablet or desktop, from the user agent. Only this class is stored, never the user agent. */
final class DeviceClassifier
{
    public function classify(string $userAgent): string
    {
        return match (true) {
            preg_match('/ipad|tablet|kindle|silk|playbook|(android(?!.*mobile))/i', $userAgent) === 1 => 'tablet',
            preg_match('/mobi|iphone|ipod|android|blackberry|opera mini|iemobile|kaios/i', $userAgent) === 1 => 'mobile',
            default => 'desktop',
        };
    }
}
