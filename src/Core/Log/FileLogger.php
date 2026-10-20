<?php

declare(strict_types=1);

namespace App\Core\Log;

/**
 * Writes one JSON line per entry to storage/logs/app-YYYY-MM-DD.log.
 * Context keys that look like secrets are redacted before writing.
 */
final class FileLogger implements LoggerInterface
{
    private const LEVELS = ['debug' => 0, 'info' => 1, 'warning' => 2, 'error' => 3];
    private const SECRET_KEY_PATTERN = '/pass|secret|token|key|authorization|cookie/i';

    public function __construct(
        private readonly string $directory,
        private readonly string $minLevel = 'info',
    ) {
    }

    public function error(string $message, array $context = []): void
    {
        $this->write('error', $message, $context);
    }

    public function warning(string $message, array $context = []): void
    {
        $this->write('warning', $message, $context);
    }

    public function info(string $message, array $context = []): void
    {
        $this->write('info', $message, $context);
    }

    public function debug(string $message, array $context = []): void
    {
        $this->write('debug', $message, $context);
    }

    /** @param array<string, mixed> $context */
    private function write(string $level, string $message, array $context): void
    {
        if (self::LEVELS[$level] < (self::LEVELS[$this->minLevel] ?? 1)) {
            return;
        }

        $line = json_encode([
            'time' => date(DATE_ATOM),
            'level' => $level,
            'message' => $message,
            'context' => $this->redact($context),
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR);

        if (!is_dir($this->directory)) {
            @mkdir($this->directory, 0775, true);
        }
        @file_put_contents($this->directory . '/app-' . date('Y-m-d') . '.log', $line . PHP_EOL, FILE_APPEND | LOCK_EX);
    }

    /**
     * @param array<string|int, mixed> $context
     * @return array<string|int, mixed>
     */
    private function redact(array $context): array
    {
        foreach ($context as $key => $value) {
            if (is_string($key) && preg_match(self::SECRET_KEY_PATTERN, $key) === 1) {
                $context[$key] = '[redacted]';
            } elseif (is_array($value)) {
                $context[$key] = $this->redact($value);
            }
        }

        return $context;
    }
}
