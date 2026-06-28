<?php

namespace App\AgentTag\Slack;

use App\AgentTag\Chat\ChatSessionReference;

final readonly class SlackSessionMapper
{
    public function map(SlackInboundEvent $event): ChatSessionReference
    {
        $threadId = '' !== $event->threadTs() ? $event->threadTs() : $event->eventTs();

        return new ChatSessionReference(
            'slack',
            $event->teamId(),
            $event->channelId(),
            $threadId,
        );
    }
}
