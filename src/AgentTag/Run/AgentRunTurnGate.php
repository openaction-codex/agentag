<?php

namespace App\AgentTag\Run;

use App\Entity\AgentRun;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

final readonly class AgentRunTurnGate
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private ?LoggerInterface $logger = null,
    ) {
    }

    /**
     * @param callable(): void|null $onWait
     */
    public function waitForTurn(AgentRun $run, int $timeoutSeconds, ?callable $onWait = null): bool
    {
        $runId = $run->id();
        if (null === $runId) {
            throw new \InvalidArgumentException('Cannot wait for an unpersisted run.');
        }

        $deadline = microtime(true) + max(1, $timeoutSeconds);
        $loggedWait = false;

        while (true) {
            $this->entityManager->refresh($run);
            if ($run->isTerminal() || $run->interruptionRequested()) {
                return false;
            }

            if (!$this->hasEarlierActiveRun($run)) {
                return true;
            }

            if (!$loggedWait) {
                $this->logger?->info('Run is waiting for an earlier active run in the same thread.', [
                    'run_id' => $runId,
                    'session_id' => $run->session()->id(),
                ]);
                $loggedWait = true;
            }

            if (null !== $onWait) {
                $onWait();
            }
            if (microtime(true) >= $deadline) {
                $this->logger?->warning('Run timed out while waiting for its thread turn.', [
                    'run_id' => $runId,
                    'session_id' => $run->session()->id(),
                    'timeout_seconds' => $timeoutSeconds,
                ]);

                return false;
            }

            usleep(500000);
        }
    }

    private function hasEarlierActiveRun(AgentRun $run): bool
    {
        $runId = $run->id();
        if (null === $runId) {
            return false;
        }

        $count = $this->entityManager->getRepository(AgentRun::class)->createQueryBuilder('earlierRun')
            ->select('COUNT(earlierRun.id)')
            ->andWhere('earlierRun.session = :session')
            ->andWhere('earlierRun.id < :runId')
            ->andWhere('earlierRun.status IN (:statuses)')
            ->setParameter('session', $run->session())
            ->setParameter('runId', $runId)
            ->setParameter('statuses', [AgentRun::STATUS_ACCEPTED, AgentRun::STATUS_RUNNING, AgentRun::STATUS_INTERRUPT_REQUESTED])
            ->getQuery()
            ->getSingleScalarResult()
        ;

        return (int) $count > 0;
    }
}
