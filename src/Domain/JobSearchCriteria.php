<?php

declare(strict_types=1);

namespace App\Domain;

/**
 * Public job-list filters, parsed from and written back to the query string, so every
 * search, filter, sort and page is a shareable URL.
 */
final class JobSearchCriteria
{
    public const SORTS = ['relevance', 'date', 'salary'];
    public const POSTED_DAYS = [1, 7, 30];

    /** @param list<JobType> $types */
    public function __construct(
        public readonly string $q = '',
        public readonly string $location = '',
        public readonly ?string $categorySlug = null,
        public readonly array $types = [],
        public readonly ?ExperienceLevel $experience = null,
        public readonly ?int $postedWithinDays = null,
        public readonly bool $remoteOnly = false,
        public readonly ?int $salaryMin = null,
        public readonly ?int $salaryMax = null,
        public readonly string $sort = 'relevance',
        public readonly int $page = 1,
    ) {
    }

    /** @param array<string, mixed> $query raw $_GET; anything invalid is ignored rather than rejected */
    public static function fromQuery(array $query, ?string $forcedCategory = null, ?string $forcedLocation = null): self
    {
        $text = static fn (string $key, int $max): string => is_string($query[$key] ?? null)
            ? mb_substr(trim((string) preg_replace('/\s+/u', ' ', $query[$key])), 0, $max)
            : '';
        $int = static function (string $key) use ($query): ?int {
            $value = $query[$key] ?? null;
            if (!is_string($value) && !is_int($value)) {
                return null;
            }
            $value = str_replace([',', ' '], '', (string) $value);

            return preg_match('/^\d{1,12}$/', $value) === 1 ? (int) $value : null;
        };

        $types = [];
        $rawTypes = $query['type'] ?? [];
        foreach (is_array($rawTypes) ? $rawTypes : [$rawTypes] as $type) {
            if (is_string($type) && ($parsed = JobType::tryFrom($type)) !== null && !in_array($parsed, $types, true)) {
                $types[] = $parsed;
            }
        }

        $category = $forcedCategory ?? (is_string($query['category'] ?? null) && preg_match('/^[a-z0-9-]{1,120}$/', $query['category']) === 1
            ? $query['category'] : null);
        $posted = $int('posted');
        $sort = is_string($query['sort'] ?? null) && in_array($query['sort'], self::SORTS, true) ? $query['sort'] : 'relevance';
        $min = $int('salary_min');
        $max = $int('salary_max');
        if ($min !== null && $max !== null && $max < $min) {
            [$min, $max] = [$max, $min];
        }

        return new self(
            $text('q', 100),
            $forcedLocation ?? $text('loc', 80),
            $category,
            $types,
            is_string($query['exp'] ?? null) ? ExperienceLevel::tryFrom($query['exp']) : null,
            in_array($posted, self::POSTED_DAYS, true) ? $posted : null,
            in_array($query['remote'] ?? null, ['1', 'on', 1, true], true),
            $min,
            $max,
            $sort,
            max(1, min(1000, $int('page') ?? 1)),
        );
    }

    /**
     * Query parameters for links. Defaults are left out to keep URLs short.
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    public function toQuery(array $overrides = [], bool $includeCategory = true, bool $includeLocation = true): array
    {
        $query = [
            'q' => $this->q,
            'loc' => $includeLocation ? $this->location : null,
            'category' => $includeCategory ? $this->categorySlug : null,
            'type' => array_map(static fn (JobType $t): string => $t->value, $this->types),
            'exp' => $this->experience?->value,
            'posted' => $this->postedWithinDays,
            'remote' => $this->remoteOnly ? '1' : null,
            'salary_min' => $this->salaryMin,
            'salary_max' => $this->salaryMax,
            'sort' => $this->sort !== 'relevance' ? $this->sort : null,
            'page' => $this->page > 1 ? $this->page : null,
            ...$overrides,
        ];

        return array_filter($query, static fn (mixed $v): bool => $v !== null && $v !== '' && $v !== []);
    }

    public function withPage(int $page): self
    {
        return new self(
            $this->q, $this->location, $this->categorySlug, $this->types, $this->experience, $this->postedWithinDays,
            $this->remoteOnly, $this->salaryMin, $this->salaryMax, $this->sort, $page,
        );
    }

    public function hasType(JobType $type): bool
    {
        return in_array($type, $this->types, true);
    }

    /** Number of filter groups in use (keyword and sort not counted), for the mobile "Filters" badge. */
    public function activeFilterCount(bool $includeCategory = true): int
    {
        return count(array_filter([
            $this->types !== [],
            $includeCategory && $this->categorySlug !== null,
            $this->experience !== null,
            $this->postedWithinDays !== null,
            $this->remoteOnly,
            $this->salaryMin !== null || $this->salaryMax !== null,
        ]));
    }

    public function hasFilters(bool $includeLocation = true): bool
    {
        return $this->toQuery(['sort' => null, 'page' => null], false, $includeLocation) !== [];
    }

    /**
     * Search words for a prefix full-text query: "front dev" -> "front:* & dev:*".
     * Only letters and digits survive, so the string is always valid tsquery syntax.
     */
    public function tsQuery(): ?string
    {
        preg_match_all('/[\p{L}\p{N}]+/u', mb_strtolower($this->q), $matches);
        $words = array_slice(array_unique($matches[0]), 0, 8);

        return $words === [] ? null : implode(' & ', array_map(static fn (string $w): string => $w . ':*', $words));
    }
}
