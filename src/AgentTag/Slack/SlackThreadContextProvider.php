<?php

namespace App\AgentTag\Slack;

use App\AgentTag\Session\ChatThreadContext;

interface SlackThreadContextProvider
{
    public function contextFor(SlackInboundEvent $event): ChatThreadContext;
}
