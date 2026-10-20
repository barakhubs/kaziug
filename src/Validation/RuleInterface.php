<?php

declare(strict_types=1);

namespace App\Validation;

interface RuleInterface
{
    /** Name used in rule strings, e.g. "max" in "max:200". */
    public function name(): string;

    /**
     * @param mixed $value already trimmed when it is a string
     * @param array<string, mixed> $data the whole input, for cross-field rules
     * @return string|null an error message, or null when the value passes
     */
    public function check(mixed $value, ?string $parameter, array $data): ?string;
}
