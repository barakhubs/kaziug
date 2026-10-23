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

    /** Font Awesome classes for the icon (self-hosted in public/assets/vendor/fontawesome). */
    public function faClass(): string
    {
        return match ($this) {
            self::Facebook => 'fa-brands fa-facebook-f',
            self::X => 'fa-brands fa-x-twitter',
            self::Instagram => 'fa-brands fa-instagram',
            self::LinkedIn => 'fa-brands fa-linkedin-in',
            self::YouTube => 'fa-brands fa-youtube',
            self::TikTok => 'fa-brands fa-tiktok',
            self::WhatsApp => 'fa-brands fa-whatsapp',
            self::Telegram => 'fa-brands fa-telegram',
            self::Website => 'fa-solid fa-globe',
        };
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
