<?php

namespace App\MessageHandler;

use App\AgentTag\Agent\AgentProfileProvider;
use App\AgentTag\Mattermost\MattermostRunProgressSink;
use App\AgentTag\Mattermost\MattermostRunProgressSinkFactory;
use App\AgentTag\Run\AgentRunTurnGate;
use App\AgentTag\Runner\AgentRunnerResult;
use App\AgentTag\Runner\AgentRunOrchestrator;
use App\AgentTag\Runner\AgentRunPromptBuilder;
use App\Entity\AgentRun;
use App\Message\RunAgentRunMessage;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DelayStamp;

#[AsMessageHandler]
final readonly class RunAgentRunMessageHandler
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private AgentProfileProvider $agentProfileProvider,
        private AgentRunPromptBuilder $promptBuilder,
        private AgentRunOrchestrator $orchestrator,
        private MattermostRunProgressSinkFactory $mattermostProgressSinkFactory,
        private AgentRunTurnGate $turnGate,
        private MessageBusInterface $messageBus,
    ) {
    }

    public function __invoke(RunAgentRunMessage $message): void
    {
        $run = $this->entityManager->getRepository(AgentRun::class)->find($message->runId());
        if (!$run instanceof AgentRun || $run->isTerminal()) {
            return;
        }

        $progressSink = $this->mattermostProgressSinkFactory->create($run);
        if ($run->deadlineExceeded()) {
            $this->fail($run, 'Task deadline exceeded before the next stage could start.', $progressSink);

            return;
        }
        if (AgentRun::STATUS_WAITING === $run->status()) {
            $wakeAt = $run->wakeAt();
            if (null !== $wakeAt && $wakeAt > new \DateTimeImmutable()) {
                $this->schedule($run, $wakeAt->getTimestamp() - time());

                return;
            }
            $run->prepareRetry('Scheduled wake: '.($run->waitReason() ?? 're-check the pending external state.'));
            $this->entityManager->flush();
        }
        if (AgentRun::STATUS_RUNNING === $run->status()) {
            $run->prepareRetry('Resume after the worker process restarted. Inspect existing workspace state before continuing.');
            $this->entityManager->flush();
        }
        if ($run->interruptionRequested() && AgentRun::INTERRUPT_CANCEL === $run->interruptionKind()) {
            $run->markInterrupted('Task stopped before the next stage started.', $run->workspacePath());
            $this->entityManager->flush();
            $progressSink->finish(new AgentRunnerResult(130, '', '', '', [], null));

            return;
        }
        if (AgentRun::STATUS_ACCEPTED !== $run->status()) {
            return;
        }

        $agent = $this->agentProfileProvider->profile();
        $workspacePath = $run->workspacePath();
        if (null === $workspacePath) {
            throw new \RuntimeException(sprintf('Task #%d has no session workspace path.', $message->runId()));
        }
        if (!$this->turnGate->waitForTurn($run, $agent->timeoutSeconds() + 30, $progressSink->onHeartbeat(...))) {
            $this->fail($run, 'Task could not start because an earlier task in this thread is still active.', $progressSink);

            return;
        }

        $steering = $run->takePendingSteering();
        $this->entityManager->flush();
        $result = $this->orchestrator->run(
            $run,
            sprintf('run-%d', $message->runId()),
            $this->promptBuilder->build($run, $steering),
            $agent->runnerMode(),
            $agent->timeoutSeconds(),
            [],
            $progressSink,
        );

        $this->entityManager->refresh($run);
        $progressSink->finish($result);
        if (AgentRun::STATUS_INTERRUPTED === $run->status()) {
            $progressSink->controlMessage('Stopped after the current command. The workspace is preserved for 24 hours; use Resume or Discard on the task card.');
        } elseif (AgentRun::STATUS_WAITING === $run->status() && null !== $run->wakeAt()) {
            $progressSink->milestone(sprintf('%s I’ll check again %s.', rtrim((string) $run->outputSummary()), $run->wakeAt()->format('Y-m-d H:i \U\T\C')));
            $this->schedule($run, max(1, $run->wakeAt()->getTimestamp() - time()));
        } elseif (AgentRun::STATUS_ACCEPTED === $run->status()) {
            $delay = AgentRun::INTERRUPT_STEER === $run->interruptionKind() ? 0 : $run->retryDelaySeconds();
            $this->schedule($run, $delay);
        }
    }

    private function schedule(AgentRun $run, int $delaySeconds): void
    {
        $runId = $run->id();
        if (null === $runId) {
            return;
        }
        $stamps = $delaySeconds > 0 ? [new DelayStamp($delaySeconds * 1000)] : [];
        $this->messageBus->dispatch(new RunAgentRunMessage($runId), $stamps);
    }

    private function fail(AgentRun $run, string $message, MattermostRunProgressSink $progressSink): void
    {
        $run->recordRunnerResult(AgentRun::STATUS_FAILED, $message, $message, $run->workspacePath() ?? '', [], 1, null);
        $this->entityManager->flush();
        $progressSink->finish(new AgentRunnerResult(1, $message, '', $message, [], null));
    }
}
