<?php

declare(strict_types=1);

namespace App\Domain;

use DateTimeImmutable;

/** A marketing popup. bodyHtml has already been through HtmlSanitizer, so it is safe to print. */
final class Popup
{
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly string $heading,
        public readonly string $bodyHtml,
        public readonly ?string $buttonLabel,
        public readonly ?string $buttonUrl,
        public readonly PopupTrigger $trigger,
        public readonly int $delaySeconds,
        public readonly ?DateTimeImmutable $startsAt,
        public readonly ?DateTimeImmutable $endsAt,
        public readonly bool $isActive,
        public readonly int $contentVersion,
        public readonly bool $hasImage,
        public readonly int $imageVersion,
        public readonly string $imageAlt,
        public readonly DateTimeImmutable $updatedAt,
    ) {
    }

    /** Active and inside its optional date window. */
    public function isShowing(?DateTimeImmutable $now = null): bool
    {
        $now ??= new DateTimeImmutable();

        return $this->isActive
            && ($this->startsAt === null || $this->startsAt <= $now)
            && ($this->endsAt === null || $this->endsAt > $now);
    }

    /** Active, Scheduled, Ended or Inactive, as the admin sees it. */
    public function statusLabel(?DateTimeImmutable $now = null): string
    {
        $now ??= new DateTimeImmutable();

        return match (true) {
            !$this->isActive => 'Inactive',
            $this->startsAt !== null && $this->startsAt > $now => 'Scheduled',
            $this->endsAt !== null && $this->endsAt <= $now => 'Ended',
            default => 'Active',
        };
    }

    /** What a browser remembers once it has shown this popup; "show again" changes it. */
    public function seenKey(): string
    {
        return "{$this->id}.{$this->contentVersion}";
    }

    public function imageUrl(): ?string
    {
        return $this->hasImage ? "/media/popups/{$this->id}/image?v={$this->imageVersion}" : null;
    }

    public function adminImageUrl(): ?string
    {
        return $this->hasImage ? "/admin/popups/{$this->id}/image?v={$this->imageVersion}" : null;
    }
}
