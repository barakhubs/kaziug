<?php

declare(strict_types=1);

namespace App\Domain;

/**
 * Job lifecycle: draft → published → closed → archived.
 * Two steps back are allowed: unpublish (published → draft) and reopen (closed → published).
 * Archived is final; archiving never deletes.
 */
enum JobStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
    case Closed = 'closed';
    case Archived = 'archived';

    /** @return list<self> */
    public function allowedNext(): array
    {
        return match ($this) {
            self::Draft => [self::Published],
            self::Published => [self::Closed, self::Draft],
            self::Closed => [self::Archived, self::Published],
            self::Archived => [],
        };
    }

    public function canTransitionTo(self $to): bool
    {
        return in_array($to, $this->allowedNext(), true);
    }

    public function label(): string
    {
        return ucfirst($this->value);
    }

    /** Verb for the button that moves a job from $this to $to. */
    public function actionLabel(self $to): string
    {
        return match (true) {
            $to === self::Published && $this === self::Closed => 'Reopen',
            $to === self::Published => 'Publish',
            $to === self::Draft => 'Unpublish',
            $to === self::Closed => 'Close',
            $to === self::Archived => 'Archive',
            default => $to->label(),
        };
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
