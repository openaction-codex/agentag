<?php

namespace App\AgentTag\Slack;

final readonly class SlackWebhookAuthenticator
{
    public function __construct(private SlackSettings $settings)
    {
    }

    public function isAllowed(SlackInboundEvent $event): bool
    {
        if ('' === $this->settings->verificationToken()) {
            return true;
        }

        return hash_equals($this->settings->verificationToken(), $event->token());
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function isUrlVerificationAllowed(array $payload): bool
    {
        if ('' === $this->settings->verificationToken()) {
            return true;
        }

        $token = $payload['token'] ?? '';
        if (!is_scalar($token)) {
            return false;
        }

        return hash_equals($this->settings->verificationToken(), trim((string) $token));
    }
}
