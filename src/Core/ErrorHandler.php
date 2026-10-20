<?php

declare(strict_types=1);

namespace App\Core;

use App\Core\Http\HttpException;
use App\Core\Http\Request;
use App\Core\Http\Response;
use App\Core\Log\LoggerInterface;
use ErrorException;
use Throwable;

/**
 * Converts throwables into responses and logs unexpected ones.
 * Stack traces are only ever shown when debug is on, and debug is forced off in production.
 */
final class ErrorHandler
{
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly View $view,
        private readonly bool $debug,
    ) {
    }

    public static function registerPhpHandlers(): void
    {
        set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
            if ((error_reporting() & $severity) === 0) {
                return false;
            }
            throw new ErrorException($message, 0, $severity, $file, $line);
        });
    }

    public function render(Throwable $e, Request $request): Response
    {
        $status = $e instanceof HttpException ? $e->status : 500;

        if ($status >= 500 && !($e instanceof HttpException)) {
            $this->logger->error($e->getMessage(), [
                'exception' => $e::class,
                'file' => $e->getFile() . ':' . $e->getLine(),
                'path' => $request->path,
                'method' => $request->method,
                'trace' => $this->debug ? null : $e->getTraceAsString(),
            ]);
        }

        $message = $e instanceof HttpException ? $e->getMessage() : 'Something went wrong on our side. Please try again later.';

        if ($this->wantsJson($request)) {
            $response = Response::json(['error' => $message], $status);
        } else {
            $response = Response::html($this->renderPage($status, $message, $e), $status);
        }

        if ($e instanceof HttpException) {
            foreach ($e->headers as $name => $value) {
                $response->setHeader($name, $value);
            }
        }

        return $response;
    }

    /** Last resort when the view layer itself may be broken. */
    public function renderPlain(Throwable $e): Response
    {
        $this->logger->error($e->getMessage(), ['exception' => $e::class, 'file' => $e->getFile() . ':' . $e->getLine()]);
        $body = $this->debug
            ? '<pre>' . e((string) $e) . '</pre>'
            : '<h1>Server error</h1><p>Something went wrong. Please try again later.</p>';

        return Response::html('<!doctype html><meta charset="utf-8"><title>Error</title>' . $body, 500);
    }

    private function renderPage(int $status, string $message, Throwable $e): string
    {
        try {
            return $this->view->render('errors/error', [
                'status' => $status,
                'message' => $message,
                'debugDetail' => $this->debug && !($e instanceof HttpException) ? (string) $e : null,
            ]);
        } catch (Throwable) {
            return '<!doctype html><meta charset="utf-8"><title>Error ' . $status . '</title><h1>' . e($message) . '</h1>';
        }
    }

    private function wantsJson(Request $request): bool
    {
        return str_contains((string) $request->header('accept'), 'application/json')
            || str_starts_with($request->path, '/health');
    }
}
