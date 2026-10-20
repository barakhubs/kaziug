<?php

declare(strict_types=1);

namespace Tests\Support\Fakes;

use App\Core\Log\LoggerInterface;

final class NullLogger implements LoggerInterface
{
    /** @var list<array{string, string}> */
    public array $entries = [];

    public function error(string $message, array $context = []): void
    {
        $this->entries[] = ['error', $message];
    }

    public function warning(string $message, array $context = []): void
    {
        $this->entries[] = ['warning', $message];
    }

    public function info(string $message, array $context = []): void
    {
        $this->entries[] = ['info', $message];
    }

    public function debug(string $message, array $context = []): void
    {
    }
}
