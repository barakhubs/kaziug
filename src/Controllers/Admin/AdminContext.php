<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Http\Request;
use App\Domain\Actor;
use App\Domain\AdminUser;

/** Reads the signed-in admin that the Authenticate middleware attached to the request. */
trait AdminContext
{
    private function admin(Request $request): AdminUser
    {
        return $request->attribute('admin');
    }

    private function actor(Request $request): Actor
    {
        return Actor::admin($this->admin($request), $request->ip());
    }

    /** Positive integer route parameter, e.g. {id}. */
    private function id(Request $request, string $name = 'id'): int
    {
        return (int) $request->attribute($name);
    }
}
