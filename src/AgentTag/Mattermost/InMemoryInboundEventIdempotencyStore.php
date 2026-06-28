<?php

namespace App\AgentTag\Mattermost;

final class InMemoryInboundEventIdempotencyStore implements InboundEventIdempotencyStore
{
    /**
     * @var array<string, true>
     */
    private array $seen = [];

    #[\Override]
    public function remember(string $eventId): bool
    {
        if (isset($this->seen[$eventId])) {
            return false;
        }

        $this->seen[$eventId] = true;

        return true;
    }
}
