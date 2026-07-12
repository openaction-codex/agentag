<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(readOnly: true)]
#[ORM\Table(name: 'inbound_event')]
#[ORM\Index(name: 'idx_inbound_event_received_at', columns: ['received_at'])]
final class InboundEvent
{
    public function __construct(
        #[ORM\Id]
        #[ORM\Column(length: 255)]
        private string $eventId,
        #[ORM\Column]
        private \DateTimeImmutable $receivedAt,
    ) {
    }
}
