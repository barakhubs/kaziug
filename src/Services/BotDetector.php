<?php

declare(strict_types=1);

namespace App\Services;

/** Recognises crawlers, link previews, monitors and scripts by their user agent. */
final class BotDetector
{
    private const BOT_PATTERN = '/bot|crawl|spider|slurp|scrape|preview|fetch|monitor|uptime|pingdom|lighthouse|headless|phantom'
        . '|facebookexternalhit|whatsapp|telegram|discord|skype|embedly|quora link|bitly|curl|wget|python|java\/|go-http|okhttp'
        . '|axios|node-fetch|guzzle|httpclient|libwww|php\/|ruby|postman|insomnia/i';

    public function isBot(string $userAgent): bool
    {
        return trim($userAgent) === '' || preg_match(self::BOT_PATTERN, $userAgent) === 1;
    }
}
