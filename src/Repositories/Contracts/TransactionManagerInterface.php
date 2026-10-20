<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use Closure;

/** Lets services group repository calls atomically without depending on the database layer. */
interface TransactionManagerInterface
{
    /**
     * @template T
     * @param Closure(): T $work
     * @return T
     */
    public function transactional(Closure $work): mixed;
}
