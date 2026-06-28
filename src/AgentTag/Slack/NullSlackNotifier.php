<?php

namespace App\AgentTag\Slack;

final readonly class NullSlackNotifier implements SlackNotifier
{
    #[\Override]
    public function showTyping(SlackInboundEvent $event): void
    {
    }

    #[\Override]
    public function postProgress(SlackInboundEvent $event, string $message): void
    {
    }
}
