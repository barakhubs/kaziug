<?php

declare(strict_types=1);

namespace Tests\Support\Fakes;

use App\Repositories\Contracts\TransactionManagerInterface;
use Closure;

final class ImmediateTransactions implements TransactionManagerInterface
{
    public function transactional(Closure $work): mixed
    {
        return $work();
    }
}
