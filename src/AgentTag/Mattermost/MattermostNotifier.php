<?php

namespace App\AgentTag\Mattermost;

interface MattermostNotifier
{
    public function showTyping(MattermostInboundEvent $event): void;

    public function postProgress(MattermostInboundEvent $event, string $message): void;
}
