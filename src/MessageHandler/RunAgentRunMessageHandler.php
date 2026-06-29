<?php

namespace App\MessageHandler;

use App\AgentTag\Agent\AgentProfileProvider;
use App\AgentTag\Mattermost\MattermostRunProgressSinkFactory;
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
}
