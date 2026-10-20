<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Log\LoggerInterface;
use App\Domain\Subscriber;
use App\Domain\SubscriberStatus;
use App\Domain\ThrottlePolicy;
use App\Mail\MailException;
use App\Repositories\Contracts\SubscriberRepositoryInterface;
use App\Services\Contracts\SubscriptionServiceInterface;
use App\Services\Contracts\ThrottleInterface;

final class SubscriptionService implements SubscriptionServiceInterface
{
    public const CONFIRM_TTL_SECONDS = 48 * 3600;

    public function __construct(
        private readonly SubscriberRepositoryInterface $subscribers,
        private readonly TokenService $tokens,
        private readonly SubscriptionMailer $mailer,
        private readonly ThrottleInterface $throttle,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function subscribe(string $email, ?string $name, string $ip): int
    {
        $key = 'subscribe:ip:' . $ip;
        $locked = $this->throttle->lockedFor($key);
        if ($locked > 0) {
            return $locked;
        }
        // Every submission counts, successful or not: the limit is on volume per IP.
        // The submission that crosses the limit is itself refused.
        $lock = $this->throttle->hit($key, ThrottlePolicy::subscribePerIp());
        if ($lock > 0) {
            return $lock;
        }

        $email = strtolower(trim($email));
        $name = $name !== null && trim($name) !== '' ? trim($name) : null;
        $existing = $this->subscribers->findByEmail($email);

        if ($existing?->status === SubscriberStatus::Active) {
            // Already confirmed: nothing to do, and the response must not reveal it.
            return 0;
        }

        $token = $this->tokens->generate();
        if ($existing === null) {
            $this->subscribers->createPending($email, $name, $ip, $this->tokens->hash($token), $this->tokens->generate());
        } else {
            $this->subscribers->restartConfirmation($existing->id, $name, $ip, $this->tokens->hash($token));
        }

        try {
            $this->mailer->sendConfirmation($email, $name, $token);
        } catch (MailException $e) {
            // The subscriber stays pending; they can submit the form again later.
            $this->logger->error('Could not send subscription confirmation', ['error' => $e->getMessage()]);
        }

        return 0;
    }

    public function confirm(string $token): ?Subscriber
    {
        if (!$this->tokens->looksValid($token)) {
            return null;
        }
        $subscriber = $this->subscribers->findPendingByConfirmHash($this->tokens->hash($token), self::CONFIRM_TTL_SECONDS);
        if ($subscriber === null) {
            return null;
        }
        $this->subscribers->activate($subscriber->id);

        return $this->subscribers->findById($subscriber->id);
    }

    public function findByUnsubscribeToken(string $token): ?Subscriber
    {
        return $this->tokens->looksValid($token) ? $this->subscribers->findByUnsubscribeToken($token) : null;
    }

    public function unsubscribe(string $token): bool
    {
        $subscriber = $this->findByUnsubscribeToken($token);
        if ($subscriber === null) {
            return false;
        }
        if ($subscriber->status !== SubscriberStatus::Unsubscribed) {
            $this->subscribers->unsubscribe($subscriber->id);
        }

        return true;
    }
}
