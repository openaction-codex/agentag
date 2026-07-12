<?php

namespace App\AgentTag\Run;

use App\AgentTag\Security\SensitiveTextRedactor;
use App\Entity\AgentRun;
use App\Entity\RunEvent;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

final readonly class RunEventRecorder
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private SensitiveTextRedactor $redactor,
        private ?LoggerInterface $logger = null,
    ) {
    }

    /**
     * @param array<string, mixed> $metadata
     */
    public function record(AgentRun $run, string $type, string $message, array $metadata = []): RunEvent
    {
        $event = new RunEvent(
            $run,
            $type,
            $this->redactor->redact($message),
            $this->redactMetadata($metadata),
            new \DateTimeImmutable(),
        );
        $this->entityManager->persist($event);
        $this->entityManager->flush();

        $this->logger?->info('Recorded agent run event.', [
            'run_id' => $run->id(),
            'type' => $type,
        ] + $event->metadata());

        return $event;
    }

    /**
     * @param array<string, mixed> $metadata
     *
     * @return array<string, mixed>
     */
    private function redactMetadata(array $metadata): array
    {
        $redacted = [];
        foreach ($metadata as $key => $value) {
            $redacted[$key] = is_string($value) ? $this->redactor->redact($value) : $value;
        }

        return $redacted;
    }
}
