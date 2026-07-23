<?php

namespace App\AgentTag\Mattermost;

use App\AgentTag\Session\ChatThreadContext;

interface MattermostThreadContextProvider
{
    public function contextFor(MattermostInboundEvent $event, string $canonicalThreadId): ChatThreadContext;
}
