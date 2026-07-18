<?php

namespace App\AgentTag\Mattermost;

use App\AgentTag\Chat\ChatSessionReference;

final readonly class MattermostSessionMapper
{
    public function __construct(private MattermostThreadRootResolver $threadRootResolver)
    {
    }

    public function map(MattermostInboundEvent $event): ChatSessionReference
    {
        $threadId = '' === $event->rootId() && in_array($event->channelType(), ['D', 'G'], true)
            ? $event->channelId()
            : $this->threadRootResolver->rootIdFor($event);

        return new ChatSessionReference(
            $event->teamId(),
            $event->channelId(),
            $threadId,
        );
    }
}
