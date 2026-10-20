<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database\Database;
use App\Repositories\Contracts\TransactionManagerInterface;
use Closure;

final class PgTransactionManager implements TransactionManagerInterface
{
    public function __construct(private readonly Database $db)
    {
    }

    public function transactional(Closure $work): mixed
    {
        return $this->db->transaction(static fn (): mixed => $work());
    }
}
