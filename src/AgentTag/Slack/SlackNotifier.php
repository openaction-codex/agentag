<?php

namespace App\AgentTag\Slack;

interface SlackNotifier
{
    public function showTyping(SlackInboundEvent $event): void;

    public function postProgress(SlackInboundEvent $event, string $message): void;
}
