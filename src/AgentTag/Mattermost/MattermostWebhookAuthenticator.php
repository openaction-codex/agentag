<?php

namespace App\AgentTag\Mattermost;

final readonly class MattermostWebhookAuthenticator
{
    public function __construct(private string $expectedToken)
    {
    }

    public function isAllowed(MattermostInboundEvent $event): bool
    {
        if ('' === $this->expectedToken) {
            return true;
        }

        return hash_equals($this->expectedToken, $event->token());
    }
}
