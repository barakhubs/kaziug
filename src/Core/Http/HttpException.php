<?php

declare(strict_types=1);

namespace App\Core\Http;

use RuntimeException;

/**
 * An expected HTTP failure (404, 403, 419, 429, ...). Its message is safe to show to users.
 */
final class HttpException extends RuntimeException
{
    /** @param array<string, string> $headers */
    public function __construct(
        public readonly int $status,
        string $message = '',
        public readonly array $headers = [],
    ) {
        parent::__construct($message !== '' ? $message : self::defaultMessage($status), $status);
    }

    public static function notFound(string $message = ''): self
    {
        return new self(404, $message);
    }

    private static function defaultMessage(int $status): string
    {
        return match ($status) {
            400 => 'Bad request.',
            403 => 'You do not have access to this page.',
            404 => 'Page not found.',
            405 => 'Method not allowed.',
            419 => 'This page expired. Go back, refresh and try again.',
            429 => 'Too many requests. Please wait and try again.',
            503 => 'The site is under maintenance. Please check back soon.',
            default => 'Something went wrong.',
        };
    }
}
