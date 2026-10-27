<?php

declare(strict_types=1);

namespace App\Domain;

/** The kind of public page a page view was on, for the analytics "pages by type" breakdown. */
enum PageType: string
{
    case Home = 'home';
    case Category = 'category';
    case City = 'city';
    case Job = 'job';
    case Page = 'page';

    public function label(): string
    {
        return match ($this) {
            self::Home => 'Home and search',
            self::Category => 'Category pages',
            self::City => 'City pages',
            self::Job => 'Job pages',
            self::Page => 'Other pages',
        };
    }
}
