<?php

declare(strict_types=1);

namespace App\Validation;

final class ValidationResult
{
    /**
     * @param array<string, mixed> $data validated, normalised values (only fields that had rules)
     * @param array<string, string> $errors first error per field
     */
    public function __construct(
        public readonly array $data,
        public readonly array $errors,
    ) {
    }

    public function passes(): bool
    {
        return $this->errors === [];
    }

    public function fails(): bool
    {
        return $this->errors !== [];
    }

    public function withError(string $field, string $message): self
    {
        return new self($this->data, [...$this->errors, $field => $this->errors[$field] ?? $message]);
    }
}
