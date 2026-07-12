<?php

namespace App\AgentTag\Mattermost;

use App\AgentTag\Chat\ChatSessionReference;

final readonly class MattermostSessionMapper
{
    public function map(MattermostInboundEvent $event): ChatSessionReference
    {
        $threadId = $event->rootId();
        if ('' === $threadId) {
            $threadId = in_array($event->channelType(), ['D', 'G'], true) ? $event->channelId() : $event->postId();
        }

        return new ChatSessionReference(
            $event->teamId(),
            $event->channelId(),
            $threadId,
        );
    }
}
