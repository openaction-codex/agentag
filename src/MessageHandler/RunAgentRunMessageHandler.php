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
    ) {
    }

    public function __invoke(RunAgentRunMessage $message): void
    {
        $run = $this->entityManager->getRepository(AgentRun::class)->find($message->runId());
        if (!$run instanceof AgentRun) {
            return;
        }

        if ($run->interruptionRequested()) {
            $run->markInterrupted('Run interrupted before the worker started it.', $run->workspacePath());
            $this->entityManager->flush();

            return;
        }

        if (AgentRun::STATUS_ACCEPTED !== $run->status()) {
            return;
        }

        $agent = $this->agentProfileProvider->profile();
        $workspacePath = $run->workspacePath();
        if (null === $workspacePath) {
            throw new \RuntimeException(sprintf('Run #%d has no session workspace path.', $message->runId()));
        }

        $progressSink = 'mattermost' === $run->session()->platform()
            ? $this->mattermostProgressSinkFactory->create($run)
            : null;

        if (!$this->turnGate->waitForTurn($run, $agent->timeoutSeconds() + 30, null === $progressSink ? null : $progressSink->onHeartbeat(...))) {
            $this->handleRunThatCouldNotStart($run, $workspacePath, $progressSink);

            return;
        }

        $result = $this->orchestrator->run(
            $run,
            sprintf('run-%d', $message->runId()),
            $this->promptBuilder->build($run),
            $agent->runnerMode(),
            $agent->timeoutSeconds(),
            [],
            $progressSink,
        );

        if (130 !== $result->exitCode()) {
            $progressSink?->finish($result);
        }
    }

    private function handleRunThatCouldNotStart(AgentRun $run, string $workspacePath, ?MattermostRunProgressSink $progressSink): void
    {
        if ($run->interruptionRequested()) {
            $run->markInterrupted('Run interrupted before it could start in this thread.', $workspacePath);
            $this->entityManager->flush();

            return;
        }

        if ($run->isTerminal()) {
            return;
        }

        $message = 'Run could not start because an earlier run in this thread is still active.';
        $run->recordRunnerResult(AgentRun::STATUS_FAILED, $message, $message, $workspacePath, [], 1, null);
        $this->entityManager->flush();

        $progressSink?->finish(new AgentRunnerResult(1, $message, '', $message, [], null));
    }
}
