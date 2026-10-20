<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\ErrorHandler;
use App\Core\Http\MiddlewareInterface;
use App\Core\Http\Request;
use App\Core\Http\Response;
use Throwable;

final class RenderErrors implements MiddlewareInterface
{
    public function __construct(private readonly ErrorHandler $errors)
    {
    }

    public function process(Request $request, callable $next): Response
    {
        try {
            return $next($request);
        } catch (Throwable $e) {
            return $this->errors->render($e, $request);
        }
    }
}
