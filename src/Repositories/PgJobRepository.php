<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database\Database;
use App\Core\Database\PgArray;
use App\Domain\Job;
use App\Domain\JobInput;
use App\Domain\JobStatus;
use App\Repositories\Contracts\JobWriteRepositoryInterface;
use DateTimeImmutable;

final class PgJobRepository implements JobWriteRepositoryInterface
{
    public function __construct(private readonly Database $db)
    {
    }

    public function adminSearch(array $filters, int $limit, int $offset): array
    {
        $where = ['j.deleted_at IS NULL'];
        $params = [];

        $q = trim((string) ($filters['q'] ?? ''));
        if ($q !== '') {
            $where[] = "(j.title ILIKE ? OR j.company ILIKE ? OR j.location ILIKE ?)";
            $like = '%' . addcslashes($q, '%_\\') . '%';
            array_push($params, $like, $like, $like);
        }
        if (($filters['status'] ?? '') !== '') {
            $where[] = 'j.status = ?';
            $params[] = $filters['status'];
        }
        if (($filters['category_id'] ?? 0) > 0) {
            $where[] = 'j.category_id = ?';
            $params[] = (int) $filters['category_id'];
        }
        $whereSql = implode(' AND ', $where);

        $total = (int) $this->db->fetchValue('SELECT count(*) FROM ' . JobRowMapper::FROM . " WHERE {$whereSql}", $params);
        $rows = $this->db->fetchAll(
            'SELECT ' . JobRowMapper::SELECT . ' FROM ' . JobRowMapper::FROM
            . " WHERE {$whereSql} ORDER BY j.updated_at DESC, j.id DESC LIMIT ? OFFSET ?",
            [...$params, $limit, $offset],
        );

        return ['items' => array_map(JobRowMapper::map(...), $rows), 'total' => $total];
    }

    public function findById(int $id): ?Job
    {
        $row = $this->db->fetchOne(
            'SELECT ' . JobRowMapper::SELECT . ' FROM ' . JobRowMapper::FROM . ' WHERE j.id = ? AND j.deleted_at IS NULL',
            [$id],
        );

        return $row === null ? null : JobRowMapper::map($row);
    }

    public function slugExists(string $slug): bool
    {
        return (bool) $this->db->fetchValue('SELECT EXISTS (SELECT 1 FROM jobs WHERE slug = ?)', [$slug]);
    }

    public function insert(JobInput $input, string $slug, JobStatus $status, ?DateTimeImmutable $publishedAt): int
    {
        return (int) $this->db->fetchValue(
            'INSERT INTO jobs (title, slug, company, location, is_remote, job_type, experience_level, category_id,
                salary_min, salary_max, currency, summary, responsibilities, requirements, skills, apply_url, apply_email,
                expires_at, status, published_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?::jsonb, ?::jsonb, ?::text[], ?, ?, ?, ?, ?)
             RETURNING id',
            [
                ...$this->fieldValues($input, $slug),
                $status->value,
                JobRowMapper::toDb($publishedAt),
            ],
        );
    }

    public function update(int $id, JobInput $input): void
    {
        $this->db->execute(
            'UPDATE jobs SET title = ?, company = ?, location = ?, is_remote = ?, job_type = ?, experience_level = ?,
                category_id = ?, salary_min = ?, salary_max = ?, currency = ?, summary = ?, responsibilities = ?::jsonb,
                requirements = ?::jsonb, skills = ?::text[], apply_url = ?, apply_email = ?, expires_at = ?
             WHERE id = ? AND deleted_at IS NULL',
            [...$this->fieldValues($input, null), $id],
        );
    }

    public function updateStatus(int $id, JobStatus $to): void
    {
        $this->db->execute(
            "UPDATE jobs SET status = ?,
                published_at = CASE WHEN ? = 'published' THEN coalesce(published_at, now()) ELSE published_at END
             WHERE id = ? AND deleted_at IS NULL",
            [$to->value, $to->value, $id],
        );
    }

    public function softDelete(int $id): void
    {
        $this->db->execute('UPDATE jobs SET deleted_at = now() WHERE id = ? AND deleted_at IS NULL', [$id]);
    }

    public function recordStatusChange(int $jobId, ?JobStatus $from, JobStatus $to, ?int $adminId): void
    {
        $this->db->execute(
            'INSERT INTO job_status_history (job_id, from_status, to_status, changed_by) VALUES (?, ?, ?, ?)',
            [$jobId, $from?->value, $to->value, $adminId],
        );
    }

    public function statusHistory(int $jobId): array
    {
        return $this->db->fetchAll(
            'SELECT h.from_status, h.to_status, a.email AS changed_by, h.changed_at
             FROM job_status_history h LEFT JOIN admin_users a ON a.id = h.changed_by
             WHERE h.job_id = ? ORDER BY h.changed_at DESC, h.id DESC',
            [$jobId],
        );
    }

    public function countByStatus(): array
    {
        $counts = array_fill_keys(JobStatus::values(), 0);
        foreach ($this->db->fetchAll('SELECT status, count(*) AS n FROM jobs WHERE deleted_at IS NULL GROUP BY status') as $row) {
            $counts[(string) $row['status']] = (int) $row['n'];
        }

        return $counts;
    }

    /** @return list<mixed> column values in the order used by insert() and update() */
    private function fieldValues(JobInput $input, ?string $slug): array
    {
        $values = [
            $input->title,
            $input->company,
            $input->location,
            $input->isRemote,
            $input->jobType->value,
            $input->experienceLevel->value,
            $input->categoryId,
            $input->salaryMin,
            $input->salaryMax,
            $input->currency,
            $input->summary,
            json_encode($input->responsibilities, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
            json_encode($input->requirements, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
            PgArray::fromList($input->skills),
            $input->applyUrl,
            $input->applyEmail,
            JobRowMapper::toDb($input->expiresAt),
        ];
        if ($slug !== null) {
            array_splice($values, 1, 0, [$slug]);
        }

        return $values;
    }
}
