<?php

declare(strict_types=1);

namespace App\Core\Session;

/**
 * Per-request session state. Does not use PHP's native session functions: loading and
 * saving is done by the StartSession middleware through a SessionStoreInterface.
 */
final class Session
{
    private const FLASH_NEW = '_flash_new';
    private const FLASH_OLD = '_flash_old';

    private bool $dirty = false;

    private ?string $previousId = null;

    /** @param array<string, mixed> $data */
    public function __construct(
        private string $id,
        private array $data = [],
        private bool $isNew = true,
    ) {
    }

    public static function generateId(): string
    {
        return bin2hex(random_bytes(32));
    }

    public static function hashId(string $id): string
    {
        return hash('sha256', $id);
    }

    public function id(): string
    {
        return $this->id;
    }

    public function isNew(): bool
    {
        return $this->isNew;
    }

    public function isDirty(): bool
    {
        return $this->dirty;
    }

    /** The id this session had before regenerate()/invalidate() in this request, if any. */
    public function previousId(): ?string
    {
        return $this->previousId;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->data[$key] ?? $default;
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->data);
    }

    public function set(string $key, mixed $value): void
    {
        $this->data[$key] = $value;
        $this->dirty = true;
    }

    public function remove(string $key): void
    {
        if (array_key_exists($key, $this->data)) {
            unset($this->data[$key]);
            $this->dirty = true;
        }
    }

    /** Stores a value readable on the next request only. */
    public function flash(string $key, mixed $value): void
    {
        $new = $this->data[self::FLASH_NEW] ?? [];
        $new[$key] = $value;
        $this->set(self::FLASH_NEW, $new);
    }

    public function getFlash(string $key, mixed $default = null): mixed
    {
        return $this->data[self::FLASH_OLD][$key] ?? $default;
    }

    /** Called once at the start of a request: last request's flash becomes readable, older flash is dropped. */
    public function ageFlash(): void
    {
        $hadOld = !empty($this->data[self::FLASH_OLD]);
        $new = $this->data[self::FLASH_NEW] ?? [];
        $this->data[self::FLASH_OLD] = $new;
        unset($this->data[self::FLASH_NEW]);
        if ($hadOld || $new !== []) {
            $this->dirty = true;
        }
    }

    /** New id, same data. Call on privilege change (login) to prevent session fixation. */
    public function regenerate(): void
    {
        $this->previousId ??= $this->id;
        $this->id = self::generateId();
        $this->isNew = true;
        $this->dirty = true;
    }

    /** New id, empty data. Call on logout. */
    public function invalidate(): void
    {
        $this->data = [];
        $this->regenerate();
    }

    /** @return array<string, mixed> */
    public function all(): array
    {
        return $this->data;
    }
}
