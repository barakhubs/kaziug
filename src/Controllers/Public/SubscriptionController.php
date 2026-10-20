<?php

declare(strict_types=1);

namespace App\Controllers\Public;

use App\Core\Http\Request;
use App\Core\Http\Responder;
use App\Core\Http\Response;
use App\Core\Log\LoggerInterface;
use App\Services\Contracts\SubscriptionServiceInterface;
use App\Services\FormTimer;
use App\Validation\Validator;

/**
 * Subscribe (double opt-in), confirm and unsubscribe. Links in emails only ever show a page
 * with a button; the change happens on POST, so link scanners can't confirm or unsubscribe.
 */
final class SubscriptionController
{
    public const HONEYPOT = 'website';

    public function __construct(
        private readonly SubscriptionServiceInterface $subscriptions,
        private readonly Validator $validator,
        private readonly FormTimer $timer,
        private readonly LoggerInterface $logger,
        private readonly Responder $responder,
    ) {
    }

    public function subscribe(Request $request): Response
    {
        $input = $this->validator->validate($request->all(), [
            'email' => 'required|email',
            'name' => 'max:100',
            'consent' => 'boolean',
        ]);
        $errors = $input->errors;
        if (!$input->data['consent']) {
            $errors['consent'] = 'Please tick the box to agree to receive job alerts.';
        }
        if ($errors !== []) {
            return $this->page('form', ['errors' => $errors, 'values' => $request->all()], 422);
        }

        // Bots: a filled honeypot or an implausibly fast or forged form. They get the normal
        // "check your inbox" page so they learn nothing, but nothing is stored or sent.
        if ($request->string(self::HONEYPOT) !== '' || !$this->timer->isHuman($request->string(FormTimer::FIELD))) {
            $this->logger->info('Subscribe form rejected as automated', ['ip' => $request->ip()]);

            return $this->page('pending');
        }

        $wait = $this->subscriptions->subscribe((string) $input->data['email'], $input->data['name'], $request->ip());
        if ($wait > 0) {
            return $this->page('rate_limited', ['minutes' => (int) ceil($wait / 60)], 429)
                ->setHeader('Retry-After', (string) $wait);
        }

        return $this->page('pending');
    }

    public function showConfirm(Request $request): Response
    {
        return $this->page('confirm', ['token' => (string) $request->attribute('token')]);
    }

    public function confirm(Request $request): Response
    {
        $subscriber = $this->subscriptions->confirm((string) $request->attribute('token'));

        return $subscriber === null ? $this->page('invalid', [], 404) : $this->page('confirmed');
    }

    public function showUnsubscribe(Request $request): Response
    {
        $token = (string) $request->attribute('token');
        $subscriber = $this->subscriptions->findByUnsubscribeToken($token);
        if ($subscriber === null) {
            return $this->page('invalid', [], 404);
        }

        return $this->page('unsubscribe', ['token' => $token, 'email' => $subscriber->email]);
    }

    /** Also the RFC 8058 one-click endpoint mail clients POST to (exempt from CSRF; the token authorises it). */
    public function unsubscribe(Request $request): Response
    {
        $ok = $this->subscriptions->unsubscribe((string) $request->attribute('token'));

        return $ok ? $this->page('unsubscribed') : $this->page('invalid', [], 404);
    }

    /** @param array<string, mixed> $data */
    private function page(string $state, array $data = [], int $status = 200): Response
    {
        return $this->responder->view('public/subscription', ['state' => $state, ...$data], $status)
            ->setHeader('X-Robots-Tag', 'noindex')
            ->setHeader('Cache-Control', 'no-store');
    }
}
