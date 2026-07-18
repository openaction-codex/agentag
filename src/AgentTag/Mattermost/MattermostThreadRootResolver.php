<?php

namespace App\AgentTag\Mattermost;

interface MattermostThreadRootResolver
{
    public function rootIdFor(MattermostInboundEvent $event): string;
}
