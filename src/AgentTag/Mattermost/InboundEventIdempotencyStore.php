<?php

namespace App\AgentTag\Mattermost;

interface InboundEventIdempotencyStore
{
    public function remember(string $eventId): bool;
}
