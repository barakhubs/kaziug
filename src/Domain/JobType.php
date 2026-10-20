<?php

declare(strict_types=1);

namespace App\Domain;

enum JobType: string
{
    case FullTime = 'full-time';
    case PartTime = 'part-time';
    case Contract = 'contract';
    case Internship = 'internship';

    public function label(): string
    {
        return match ($this) {
            self::FullTime => 'Full-time',
            self::PartTime => 'Part-time',
            self::Contract => 'Contract',
            self::Internship => 'Internship',
        };
    }

    /** Value for schema.org JobPosting.employmentType. */
    public function schemaOrg(): string
    {
        return match ($this) {
            self::FullTime => 'FULL_TIME',
            self::PartTime => 'PART_TIME',
            self::Contract => 'CONTRACTOR',
            self::Internship => 'INTERN',
        };
    }

    public static function fromLabel(string $label): self
    {
        return self::from(strtolower(trim($label)));
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
