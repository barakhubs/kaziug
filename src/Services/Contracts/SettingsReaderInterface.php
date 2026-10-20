<?php

declare(strict_types=1);

namespace App\Services\Contracts;

use App\Domain\SiteSettings;

/** Read side of site settings: used on every page, so it must never throw. */
interface SettingsReaderInterface
{
    public function site(): SiteSettings;
}
