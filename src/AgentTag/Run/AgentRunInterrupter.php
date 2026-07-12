<?php

namespace App\AgentTag\Run;

use App\AgentTag\Chat\ChatSessionReference;
use App\Entity\AgentRun;
use App\Entity\ChatSession;
use App\Entity\RunEvent;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

final readonly class AgentRunInterrupter implements RunInterrupter
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private ?RunEventRecorder $runEventRecorder = null,
        private ?LoggerInterface $logger = null,
    ) {
    }

    #[\Override]
    public function cancelActiveRun(ChatSessionReference $reference, string $sourceEventId, string $requesterId): ?AgentRun
    {
        $run = $this->activeRun($reference);
        if (null === $run) {
            return null;
        }

        $run->requestCancellation();
        $this->recordControl($run, RunEvent::TYPE_CANCELLATION_REQUESTED, 'Cancellation requested.', $reference, $sourceEventId, $requesterId);

        return $run;
    }

    #[\Override]
    public function steerActiveRun(ChatSessionReference $reference, string $instruction, string $sourceEventId, string $requesterId): ?AgentRun
    {
        $run = $this->activeRun($reference);
        if (null === $run) {
            return null;
        }

        $run->requestSteering($instruction);
        $this->recordControl($run, RunEvent::TYPE_STEERING_RECEIVED, 'Steering message queued for the active task.', $reference, $sourceEventId, $requesterId);

        return $run;
    }

    #[\Override]
    public function retryLatestRun(ChatSessionReference $reference, string $instruction): ?AgentRun
    {
        $session = $this->session($reference);
        if (null === $session) {
            return null;
        }
        $run = $this->entityManager->getRepository(AgentRun::class)->findOneBy(['session' => $session], ['id' => 'DESC']);
        if (!$run instanceof AgentRun || !$run->isTerminal()) {
            return null;
        }

        $run->prepareRetry($instruction);
        $this->runEventRecorder?->record($run, RunEvent::TYPE_RETRY_REQUESTED, 'Task retry requested.', [
            'thread_id' => $reference->threadId(),
        ]);
        $this->entityManager->flush();

        return $run;
    }

    private function activeRun(ChatSessionReference $reference): ?AgentRun
    {
        $session = $this->session($reference);
        if (null === $session) {
            return null;
        }

        $run = $this->entityManager->getRepository(AgentRun::class)->createQueryBuilder('run')
            ->andWhere('run.session = :session')
            ->andWhere('run.status IN (:statuses)')
            ->setParameter('session', $session)
            ->setParameter('statuses', [AgentRun::STATUS_ACCEPTED, AgentRun::STATUS_RUNNING, AgentRun::STATUS_WAITING, AgentRun::STATUS_INTERRUPT_REQUESTED])
            ->orderBy('run.id', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        return $run instanceof AgentRun ? $run : null;
    }

    private function session(ChatSessionReference $reference): ?ChatSession
    {
        $session = $this->entityManager->getRepository(ChatSession::class)->findOneBy(['sessionKey' => $reference->key()]);

        return $session instanceof ChatSession ? $session : null;
    }

    private function recordControl(AgentRun $run, string $type, string $message, ChatSessionReference $reference, string $sourceEventId, string $requesterId): void
    {
        $this->runEventRecorder?->record($run, $type, $message, [
            'thread_id' => $reference->threadId(),
            'source_event_id' => $sourceEventId,
            'requester_id' => $requesterId,
        ]);
        $this->entityManager->flush();
        $this->logger?->info($message, ['run_id' => $run->id(), 'source_event_id' => $sourceEventId]);
    }
}
