<?php

namespace App\AgentTag\Mattermost;

final readonly class NullMattermostNotifier implements MattermostNotifier
{
    #[\Override]
    public function showTyping(MattermostInboundEvent $event): void
    {
    }

    #[\Override]
    public function postProgress(MattermostInboundEvent $event, string $message): void
    {
    }
}
