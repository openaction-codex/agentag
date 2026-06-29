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
    public function interruptActiveRuns(ChatSessionReference $reference, string $sourceEventId, string $requesterId): int
    {
        $session = $this->entityManager->getRepository(ChatSession::class)->findOneBy([
            'sessionKey' => $reference->key(),
        ]);
        if (!$session instanceof ChatSession) {
            return 0;
        }

        $runs = $this->entityManager->getRepository(AgentRun::class)->createQueryBuilder('agentRun')
            ->andWhere('agentRun.session = :session')
            ->andWhere('agentRun.status IN (:statuses)')
            ->andWhere('agentRun.sourceEventId IS NULL OR agentRun.sourceEventId <> :sourceEventId')
            ->setParameter('session', $session)
            ->setParameter('statuses', [AgentRun::STATUS_ACCEPTED, AgentRun::STATUS_RUNNING, AgentRun::STATUS_INTERRUPT_REQUESTED])
            ->setParameter('sourceEventId', $sourceEventId)
            ->orderBy('agentRun.id', 'DESC')
            ->getQuery()
            ->toIterable()
        ;

        $count = 0;
        foreach ($runs as $run) {
            if (!$run instanceof AgentRun || $run->isTerminal()) {
                continue;
            }

            $run->requestInterruption();
            ++$count;
            $this->runEventRecorder?->record($run, RunEvent::TYPE_INTERRUPTION_REQUESTED, 'Interruption requested by a newer Mattermost message.', [
                'platform' => $reference->platform(),
                'thread_id' => $reference->threadId(),
                'source_event_id' => $sourceEventId,
                'requester_id' => $requesterId,
            ]);
        }

        if ($count > 0) {
            $this->entityManager->flush();
            $this->logger?->info('Requested active run interruption.', [
                'session_key' => $reference->key(),
                'source_event_id' => $sourceEventId,
                'requester_id' => $requesterId,
                'count' => $count,
            ]);
        }

        return $count;
    }
}
