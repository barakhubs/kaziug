<?php

declare(strict_types=1);

namespace App\Console;

/**
 * Terminal input/output. Tests pass scripted answers instead of reading STDIN.
 */
class Io
{
    /** @var list<string> */
    public array $output = [];

    /** @param list<string>|null $answers scripted answers (tests); null reads the terminal */
    public function __construct(private ?array $answers = null, private readonly bool $echo = true)
    {
    }

    public function line(string $text = ''): void
    {
        $this->output[] = $text;
        if ($this->echo) {
            fwrite(STDOUT, $text . PHP_EOL);
        }
    }

    public function error(string $text): void
    {
        $this->output[] = $text;
        if ($this->echo) {
            fwrite(STDERR, $text . PHP_EOL);
        }
    }

    public function ask(string $question): string
    {
        if ($this->answers !== null) {
            return trim((string) array_shift($this->answers));
        }
        fwrite(STDOUT, $question . ' ');

        return trim((string) fgets(STDIN));
    }

    /** Reads without echoing where the terminal allows it (stty on Unix-like systems). */
    public function askHidden(string $question): string
    {
        if ($this->answers !== null) {
            return (string) array_shift($this->answers);
        }

        fwrite(STDOUT, $question . ' ');
        $canHide = DIRECTORY_SEPARATOR === '/' && stream_isatty(STDIN);
        if ($canHide) {
            shell_exec('stty -echo');
        }
        try {
            $value = rtrim((string) fgets(STDIN), "\r\n");
        } finally {
            if ($canHide) {
                shell_exec('stty echo');
                fwrite(STDOUT, PHP_EOL);
            }
        }

        return $value;
    }
}
