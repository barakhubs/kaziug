<?php

declare(strict_types=1);

namespace App\Domain;

use RuntimeException;

/**
 * A business rule was violated. The message is written for the admin and safe to display.
 * $field optionally names the form field the problem belongs to.
 */
class DomainException extends RuntimeException
{
    public function __construct(string $message, public readonly ?string $field = null)
    {
        parent::__construct($message);
    }
}
