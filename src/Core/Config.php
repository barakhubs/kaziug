<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Read-only configuration tree, addressed with dot keys ("app.env").
 */
final class Config
{
    /** @param array<string, mixed> $values */
    public function __construct(private readonly array $values)
    {
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $node = $this->values;
        foreach (explode('.', $key) as $segment) {
            if (!is_array($node) || !array_key_exists($segment, $node)) {
                return $default;
            }
            $node = $node[$segment];
        }

        return $node;
    }

    public function string(string $key, string $default = ''): string
    {
        return (string) $this->get($key, $default);
    }

    public function int(string $key, int $default = 0): int
    {
        return (int) $this->get($key, $default);
    }

    public function bool(string $key, bool $default = false): bool
    {
        return (bool) $this->get($key, $default);
    }

    /** A copy with one dot-key replaced (used by tests and commands that need a variation). */
    public function with(string $key, mixed $value): self
    {
        $values = $this->values;
        $node = &$values;
        foreach (explode('.', $key) as $segment) {
            if (!isset($node[$segment]) || !is_array($node[$segment])) {
                $node[$segment] = $node[$segment] ?? [];
            }
            $node = &$node[$segment];
        }
        $node = $value;

        return new self($values);
    }

    public function isProduction(): bool
    {
        return $this->get('app.env') === 'production';
    }
}
