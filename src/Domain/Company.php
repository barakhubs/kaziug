<?php

declare(strict_types=1);

namespace App\Domain;

/** An employer that jobs are posted for. The logo bytes live in the database and are served separately. */
final class Company
{
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly ?string $website,
        public readonly bool $hasLogo,
        public readonly int $logoVersion,
        /** Jobs referencing the company, soft-deleted ones included. */
        public readonly int $jobCount = 0,
    ) {
    }

    public static function logoPath(int $id, int $version): string
    {
        return "/media/companies/{$id}/logo?v={$version}";
    }

    public function logoUrl(): ?string
    {
        return $this->hasLogo ? self::logoPath($this->id, $this->logoVersion) : null;
    }

    /** @return array{id: int, name: string, logoUrl: ?string} the shape the admin company picker uses */
    public function toPickerArray(): array
    {
        return ['id' => $this->id, 'name' => $this->name, 'logoUrl' => $this->logoUrl()];
    }
}
