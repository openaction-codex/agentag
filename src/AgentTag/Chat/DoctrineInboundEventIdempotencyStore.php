<?php

namespace App\AgentTag\Chat;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Types\Types;

final readonly class DoctrineInboundEventIdempotencyStore implements InboundEventIdempotencyStore
{
    public function __construct(private Connection $connection)
    {
    }

    #[\Override]
    public function remember(string $eventId): bool
    {
        return 1 === $this->connection->executeStatement(
            'INSERT INTO inbound_event (event_id, received_at) VALUES (:event_id, :received_at) ON CONFLICT (event_id) DO NOTHING',
            ['event_id' => $eventId, 'received_at' => new \DateTimeImmutable()],
            ['event_id' => ParameterType::STRING, 'received_at' => Types::DATETIME_IMMUTABLE],
        );
    }
}
