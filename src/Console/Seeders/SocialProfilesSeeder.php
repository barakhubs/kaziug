<?php

declare(strict_types=1);

namespace App\Console\Seeders;

use App\Console\Io;
use App\Repositories\Contracts\SocialProfileRepositoryInterface;

/** Placeholder profiles pointing at example pages; replace the URLs in the back office. */
final class SocialProfilesSeeder implements SeederInterface
{
    private const PROFILES = [
        ['platform' => 'Facebook', 'label' => 'KaziUG on Facebook', 'url' => 'https://www.facebook.com/example', 'icon_key' => 'facebook'],
        ['platform' => 'X', 'label' => 'KaziUG on X', 'url' => 'https://x.com/example', 'icon_key' => 'x'],
        ['platform' => 'LinkedIn', 'label' => 'KaziUG on LinkedIn', 'url' => 'https://www.linkedin.com/company/example', 'icon_key' => 'linkedin'],
        ['platform' => 'Instagram', 'label' => 'KaziUG on Instagram', 'url' => 'https://www.instagram.com/example', 'icon_key' => 'instagram'],
        ['platform' => 'WhatsApp', 'label' => 'KaziUG WhatsApp channel', 'url' => 'https://whatsapp.com/channel/example', 'icon_key' => 'whatsapp'],
    ];

    public function __construct(private readonly SocialProfileRepositoryInterface $profiles)
    {
    }

    public function name(): string
    {
        return 'social';
    }

    public function run(Io $io): void
    {
        $created = 0;
        foreach (self::PROFILES as $profile) {
            if (!$this->profiles->existsForPlatform($profile['platform'])) {
                $this->profiles->create([...$profile, 'is_visible' => true]);
                $created++;
            }
        }
        $io->line("Social profiles: {$created} created, " . (count(self::PROFILES) - $created) . ' already present.');
    }
}
