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

    /** 24×24 SVG path data, drawn with fill="currentColor". */
    public function svgPath(): string
    {
        return match ($this) {
            self::Facebook => 'M14 8h3V4h-3c-2.8 0-4 1.9-4 4.3V10H7v4h3v8h4v-8h3l1-4h-4V8.6c0-.4.3-.6.6-.6Z',
            self::X => 'M4 4l6.6 8.8L4.3 20H6l5.4-6.1L15.8 20H20l-6.9-9.2L19 4h-1.7l-5 5.7L8.2 4H4Z',
            self::Instagram => 'M7 3h10a4 4 0 0 1 4 4v10a4 4 0 0 1-4 4H7a4 4 0 0 1-4-4V7a4 4 0 0 1 4-4Zm0 2a2 2 0 0 0-2 2v10a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2H7Zm5 3.5a3.5 3.5 0 1 1 0 7 3.5 3.5 0 0 1 0-7Zm0 2a1.5 1.5 0 1 0 0 3 1.5 1.5 0 0 0 0-3Zm5.3-4.8a1 1 0 1 1 0 2 1 1 0 0 1 0-2Z',
            self::LinkedIn => 'M4 9h3.5v11H4V9Zm1.75-5.5a2 2 0 1 1 0 4 2 2 0 0 1 0-4ZM9.5 9h3.4v1.6c.5-.9 1.7-1.9 3.5-1.9 3.6 0 4.3 2.3 4.3 5.4V20h-3.5v-5.3c0-1.3 0-2.9-1.8-2.9s-2.1 1.4-2.1 2.8V20H9.5V9Z',
            self::YouTube => 'M21.6 7.2a2.5 2.5 0 0 0-1.8-1.8C18.2 5 12 5 12 5s-6.2 0-7.8.4a2.5 2.5 0 0 0-1.8 1.8C2 8.8 2 12 2 12s0 3.2.4 4.8a2.5 2.5 0 0 0 1.8 1.8C5.8 19 12 19 12 19s6.2 0 7.8-.4a2.5 2.5 0 0 0 1.8-1.8c.4-1.6.4-4.8.4-4.8s0-3.2-.4-4.8ZM10 15V9l5.2 3L10 15Z',
            self::TikTok => 'M16.5 3c.3 2.2 1.6 3.6 3.5 3.8v3.1c-1.3.1-2.5-.3-3.5-1v6.1c0 3.6-3.2 5.9-6.4 5-3.9-1.1-4.6-6.6-1-8.6 1-.6 2.2-.8 3.4-.6v3.2c-.4-.1-.8-.2-1.2-.1-1.4.2-2.2 1.6-1.6 2.9.7 1.4 2.9 1.4 3.4-.5.1-.3.1-.7.1-1V3h3.3Z',
            self::WhatsApp => 'M12 2a10 10 0 0 0-8.6 15.1L2 22l5-1.3A10 10 0 1 0 12 2Zm0 2a8 8 0 1 1-4.2 14.8l-.3-.2-2.6.7.7-2.5-.2-.3A8 8 0 0 1 12 4Zm-3 3.8c-.2 0-.6.1-.9.4-.3.3-1.1 1.1-1.1 2.6s1.1 3 1.3 3.2c.2.2 2.2 3.4 5.4 4.6 2.6 1 3.2.8 3.7.8.6-.1 1.8-.7 2-1.4.3-.7.3-1.3.2-1.4l-.6-.4-2-1c-.3-.1-.5-.1-.7.1l-.9 1.1c-.2.2-.4.2-.6.1-.3-.1-1.2-.5-2.3-1.4-.8-.8-1.4-1.7-1.5-2-.2-.3 0-.4.1-.6l.5-.6.3-.5v-.5l-.9-2.2c-.2-.6-.5-.5-.7-.5H9Z',
            self::Telegram => 'M21.5 4.2 2.9 11.4c-1.3.5-1.2 1.2-.2 1.5l4.8 1.5 1.8 5.6c.2.6.4.8.9.8.4 0 .6-.2.9-.4l2.3-2.2 4.7 3.5c.9.5 1.5.2 1.7-.8l3.1-14.7c.3-1.3-.5-1.9-1.4-1.6ZM8.6 14.1l9.6-6.1c.5-.3.9-.1.5.2l-7.9 7.1-.3 3.3-1.9-4.5Z',
            self::Website => 'M12 2a10 10 0 1 0 0 20 10 10 0 0 0 0-20Zm6.9 6h-3a15.7 15.7 0 0 0-1.4-3.6A8 8 0 0 1 18.9 8ZM12 4c.8 1.2 1.5 2.5 1.9 4h-3.8c.4-1.5 1.1-2.8 1.9-4ZM4.3 14a8.2 8.2 0 0 1 0-4h3.4a16.5 16.5 0 0 0 0 4H4.3Zm.8 2h3a15.7 15.7 0 0 0 1.4 3.6A8 8 0 0 1 5.1 16Zm3-8h-3a8 8 0 0 1 4.4-3.6C8.9 5.5 8.4 6.7 8.1 8ZM12 20c-.8-1.2-1.5-2.5-1.9-4h3.8c-.4 1.5-1.1 2.8-1.9 4Zm2.3-6H9.7a14.6 14.6 0 0 1 0-4h4.6a14.6 14.6 0 0 1 0 4Zm.3 5.6c.6-1.1 1.1-2.3 1.4-3.6h3a8 8 0 0 1-4.4 3.6Zm1.7-5.6a16.5 16.5 0 0 0 0-4h3.4a8.2 8.2 0 0 1 0 4h-3.4Z',
        };
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
