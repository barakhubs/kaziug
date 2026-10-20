<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Console\CommandInterface;
use App\Console\Io;
use App\Services\NewsletterWorker;

/**
 * Runs the newsletter sender. Without options it does one batch and exits (for cron or a
 * scheduler that runs it every minute). With --loop it keeps running, one batch per minute
 * (for a long-running background process, as on Laravel Cloud).
 */
final class NewsletterWorkCommand implements CommandInterface
{
    public function __construct(private readonly NewsletterWorker $worker)
    {
    }

    public function name(): string
    {
        return 'newsletter:work';
    }

    public function description(): string
    {
        return 'Send queued newsletter emails (one batch; --loop to run every minute)';
    }

    public function run(array $args, Io $io): int
    {
        $loop = in_array('--loop', $args, true);
        $maxRuns = null;
        foreach ($args as $arg) {
            if (str_starts_with($arg, '--max-runs=')) {
                $maxRuns = max(1, (int) substr($arg, 11));
            }
        }

        $runs = 0;
        do {
            $started = time();
            $stats = $this->worker->run();
            $runs++;
            $io->line(sprintf(
                '[%s] %s',
                date('Y-m-d H:i:s'),
                $stats['locked']
                    ? 'Another worker is running; skipped.'
                    : "promoted {$stats['promoted']}, sent {$stats['sent']}, retrying {$stats['retrying']}, failed {$stats['failed']}, finished {$stats['finished']}",
            ));

            if ($loop && ($maxRuns === null || $runs < $maxRuns)) {
                sleep(max(1, 60 - (time() - $started)));
            }
        } while ($loop && ($maxRuns === null || $runs < $maxRuns));

        return 0;
    }
}
