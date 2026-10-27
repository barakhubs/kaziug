<?php

declare(strict_types=1);

namespace App\Domain;

/** The breakdowns analytics_daily is rolled up by. Values match its CHECK constraint. */
enum AnalyticsDimension: string
{
    case Total = 'total';
    case Path = 'path';
    case PageType = 'page_type';
    case Job = 'job';
    case Category = 'category';
    case Referrer = 'referrer';
    case UtmSource = 'utm_source';
    case UtmCampaign = 'utm_campaign';
    case Device = 'device';
    case Search = 'search';
    case Filter = 'filter';
    case Channel = 'channel';
}
