<?php

declare(strict_types=1);

namespace App\Repositories;

/**
 * Turns search words into an InnoDB FULLTEXT query plus a fallback for short words.
 * The index skips words shorter than innodb_ft_min_token_size (3 by default) and shared hosts do not
 * let us change that, so short words like "HR" or "QA" are matched with a word-start REGEXP instead.
 * Stopwords are dropped, as the PostgreSQL search did, so "the" alone does not filter anything.
 */
final class FullTextQuery
{
    private const MIN_TOKEN = 3;

    /** InnoDB's default stopword list (INFORMATION_SCHEMA.INNODB_FT_DEFAULT_STOPWORD). */
    private const STOPWORDS = [
        'a', 'about', 'an', 'are', 'as', 'at', 'be', 'by', 'com', 'de', 'en', 'for', 'from', 'how', 'i', 'in',
        'is', 'it', 'la', 'of', 'on', 'or', 'that', 'the', 'this', 'to', 'was', 'what', 'when', 'where', 'who',
        'will', 'with', 'und', 'www',
    ];

    /** Boolean-mode query requiring every indexable word as a prefix: "+front* +developer*", or null. */
    public readonly ?string $against;

    /** @var list<string> REGEXP patterns, one per short word, each matching the start of a word */
    public readonly array $shortWordPatterns;

    /** @param list<string> $words lower-case letters and digits only (JobSearchCriteria::searchWords) */
    public function __construct(array $words)
    {
        $indexed = [];
        $short = [];
        foreach ($words as $word) {
            if (in_array($word, self::STOPWORDS, true)) {
                continue;
            }
            if (mb_strlen($word) >= self::MIN_TOKEN) {
                $indexed[] = '+' . $word . '*';
            } else {
                $short[] = '\\b' . $word;
            }
        }
        $this->against = $indexed === [] ? null : implode(' ', $indexed);
        $this->shortWordPatterns = $short;
    }

    /**
     * Text stored in jobs.search_text. The title appears twice so that, as with the old weighted
     * PostgreSQL search, a match in the title ranks above a match in the summary.
     * @param list<string> $skills
     */
    public static function document(string $title, array $skills, string $company, string $summary): string
    {
        return implode("\n", [$title, $title, implode(' ', $skills), $company, $summary]);
    }
}
