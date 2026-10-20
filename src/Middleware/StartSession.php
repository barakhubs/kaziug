<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Config;
use App\Core\CookieFactory;
use App\Core\Http\MiddlewareInterface;
use App\Core\Http\Request;
use App\Core\Http\Response;
use App\Core\Session\Session;
use App\Core\Session\SessionStoreInterface;

/**
 * Loads the session from its cookie and saves it after the response is built.
 * Sessions are lazy: a session with no data is never stored and gets no cookie.
 */
final class StartSession implements MiddlewareInterface
{
    // Re-save an unchanged session at most this often to keep it alive.
    private const TOUCH_SECONDS = 300;
    private const GC_PROBABILITY = 100;

    public function __construct(
        private readonly SessionStoreInterface $store,
        private readonly Config $config,
        private readonly CookieFactory $cookies,
    ) {
    }

    public function process(Request $request, callable $next): Response
    {
        $lifetime = $this->config->int('session.lifetime_minutes', 120) * 60;
        $cookieName = $this->config->string('session.cookie', 'kaziug_session');
        $cookieId = $request->cookie($cookieName);

        $data = null;
        if (is_string($cookieId) && preg_match('/^[a-f0-9]{64}$/', $cookieId) === 1) {
            $data = $this->store->read(Session::hashId($cookieId), $lifetime);
        }
        $session = $data !== null
            ? new Session((string) $cookieId, $data, false)
            : new Session(Session::generateId());
        $session->ageFlash();
        $request->setAttribute('session', $session);

        $response = $next($request);

        $this->persist($session, $request, $response, $cookieName, $cookieId !== null);
        if (random_int(1, self::GC_PROBABILITY) === 1) {
            $this->store->gc($lifetime);
        }

        return $response;
    }

    private function persist(Session $session, Request $request, Response $response, string $cookieName, bool $hadCookie): void
    {
        if ($session->previousId() !== null) {
            $this->store->destroy(Session::hashId($session->previousId()));
        }

        $data = $session->all();
        unset($data['_flash_old']);
        $isEmpty = array_diff_key($data, ['_touched' => true]) === [];

        if ($isEmpty) {
            if (!$session->isNew()) {
                $this->store->destroy(Session::hashId($session->id()));
            }
            if ($hadCookie) {
                $response->setCookie($cookieName, '', $this->cookies->options($request, time() - 3600));
            }

            return;
        }

        $stale = time() - (int) $session->get('_touched', 0) > self::TOUCH_SECONDS;
        if ($session->isDirty() || $stale || $session->isNew()) {
            $session->set('_touched', time());
            $this->store->write(Session::hashId($session->id()), $session->all());
        }
        if ($session->isNew()) {
            $response->setCookie($cookieName, $session->id(), $this->cookies->options($request));
        }
    }
}
