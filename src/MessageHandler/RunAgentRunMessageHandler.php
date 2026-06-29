<?php

namespace App\MessageHandler;

use App\AgentTag\Agent\AgentProfileProvider;
use App\AgentTag\Codebase\CodebaseContextPreparer;
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
        private CodebaseContextPreparer $codebaseContextPreparer,
        private AgentRunPromptBuilder $promptBuilder,
        private AgentRunOrchestrator $orchestrator,
        private MattermostRunProgressSinkFactory $mattermostProgressSinkFactory,
    ) {
    }

    public function __invoke(RunAgentRunMessage $message): void
    {
        $run = $this->entityManager->getRepository(AgentRun::class)->find($message->runId());
        if (!$run instanceof AgentRun || 'accepted' !== $run->status()) {
            return;
        }

        $agent = $this->agentProfileProvider->profile();
        $workspacePath = $run->workspacePath();
        if (null === $workspacePath) {
            throw new \RuntimeException(sprintf('Run #%d has no session workspace path.', $message->runId()));
        }

        $codebaseContext = $this->codebaseContextPreparer->prepare($workspacePath, $run);
        $progressSink = 'mattermost' === $run->session()->platform()
            ? $this->mattermostProgressSinkFactory->create($run)
            : null;

        $result = $this->orchestrator->run(
            $run,
            sprintf('run-%d', $message->runId()),
            $this->promptBuilder->build($run, $codebaseContext),
            $agent->runnerMode(),
            $agent->timeoutSeconds(),
            [],
            $progressSink,
        );

        $progressSink?->finish($result);
    }
}
