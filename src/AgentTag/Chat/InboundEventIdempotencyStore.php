<?php

namespace App\AgentTag\Chat;

interface InboundEventIdempotencyStore
{
    public function remember(string $eventId): bool;
}
