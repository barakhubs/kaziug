<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database\Database;
use App\Repositories\Contracts\VisitorSaltRepositoryInterface;
use DateTimeImmutable;

final class MysqlVisitorSaltRepository implements VisitorSaltRepositoryInterface
{
    public function __construct(private readonly Database $db)
    {
    }

    public function saltFor(DateTimeImmutable $day): string
    {
        $date = $day->format('Y-m-d');
        $salt = $this->find($date);
        if ($salt !== null) {
            return $salt;
        }

        // Several replicas can race on the first request of the day; the first insert wins.
        $this->db->execute(
            'INSERT INTO analytics_salts (day, salt) VALUES (?, UNHEX(?)) ON DUPLICATE KEY UPDATE day = day',
            [$date, bin2hex(random_bytes(32))],
        );
        $this->db->execute('DELETE FROM analytics_salts WHERE day < ?', [$date]);

        return (string) $this->find($date);
    }

    private function find(string $date): ?string
    {
        $value = $this->db->fetchValue('SELECT HEX(salt) FROM analytics_salts WHERE day = ?', [$date]);

        return is_string($value) ? (string) hex2bin($value) : null;
    }
}
