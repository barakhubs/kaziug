<?php

declare(strict_types=1);

namespace App\Domain;

/** The fixed list of icons a social profile can use (mirrors the CHECK constraint). */
enum SocialIcon: string
{
    case Facebook = 'facebook';
    case X = 'x';
    case Instagram = 'instagram';
    case LinkedIn = 'linkedin';
    case YouTube = 'youtube';
    case TikTok = 'tiktok';
    case WhatsApp = 'whatsapp';
    case Telegram = 'telegram';
    case Website = 'website';

    public function label(): string
    {
        return match ($this) {
            self::Facebook => 'Facebook',
            self::X => 'X (Twitter)',
            self::Instagram => 'Instagram',
            self::LinkedIn => 'LinkedIn',
            self::YouTube => 'YouTube',
            self::TikTok => 'TikTok',
            self::WhatsApp => 'WhatsApp',
            self::Telegram => 'Telegram',
            self::Website => 'Website',
        };
    }

    /** The inline SVG icon name (a file in views/icons), rendered by App\Core\Icon. */
    public function iconName(): string
    {
        return match ($this) {
            self::Facebook => 'brands/facebook-f',
            self::X => 'brands/x-twitter',
            self::Instagram => 'brands/instagram',
            self::LinkedIn => 'brands/linkedin-in',
            self::YouTube => 'brands/youtube',
            self::TikTok => 'brands/tiktok',
            self::WhatsApp => 'brands/whatsapp',
            self::Telegram => 'brands/telegram',
            self::Website => 'solid/globe',
        };
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
