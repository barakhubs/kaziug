<?php

declare(strict_types=1);

namespace App\Core\Http;

use App\Core\Session\Session;
use App\Core\View;

/**
 * Builds the responses controllers return, so controllers stay one-liners around a service call.
 */
final class Responder
{
    public function __construct(private readonly View $view)
    {
    }

    /** @param array<string, mixed> $data */
    public function view(string $template, array $data = [], int $status = 200): Response
    {
        return Response::html($this->view->render($template, $data), $status);
    }

    public function redirect(string $location): Response
    {
        return Response::redirect($location);
    }

    /** Post/Redirect/Get with a one-time message (type: success, error or info). */
    public function redirectWith(Request $request, string $location, string $type, string $message): Response
    {
        /** @var Session $session */
        $session = $request->attribute('session');
        $session->flash($type, $message);

        return Response::redirect($location);
    }
}
