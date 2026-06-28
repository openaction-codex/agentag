<?php

namespace App\AgentTag\Slack;

use App\AgentTag\Session\ChatThreadContext;

final readonly class CurrentSlackThreadContextProvider implements SlackThreadContextProvider
{
    #[\Override]
    public function contextFor(SlackInboundEvent $event): ChatThreadContext
    {
        return ChatThreadContext::single($event->eventTs(), $event->userId(), $event->text());
    }
}
