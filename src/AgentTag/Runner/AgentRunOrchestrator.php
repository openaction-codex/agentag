<?php

namespace App\AgentTag\Runner;

use App\AgentTag\Security\SensitiveTextRedactor;
use App\AgentTag\Workspace\WorkspaceLayout;
use App\Entity\AgentRun;
use Doctrine\ORM\EntityManagerInterface;

final readonly class AgentRunOrchestrator
{
    public function __construct(
        private AgentRunnerInterface $runner,
        private WorkspaceLayout $workspaceLayout,
        private SensitiveTextRedactor $redactor,
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @param array<string, string> $environment
     */
    public function run(AgentRun $run, string $runIdentifier, string $prompt, string $runnerMode, int $timeoutSeconds, array $environment = []): AgentRunnerResult
    {
        $workingDirectory = $this->workspaceLayout->runPath($runIdentifier);
        $artifactsDirectory = $this->workspaceLayout->artifactsPath($runIdentifier);

        if (!is_dir($workingDirectory)) {
            mkdir($workingDirectory, 0777, true);
        }
        if (!is_dir($artifactsDirectory)) {
            mkdir($artifactsDirectory, 0777, true);
        }

        $result = $this->runner->run(new AgentRunnerInput(
            $prompt,
            $workingDirectory,
            $artifactsDirectory,
            $environment,
            $timeoutSeconds,
            $runnerMode,
        ));

        $run->recordRunnerResult(
            $result->successful() ? 'completed' : 'failed',
            $this->redactor->redact($result->finalMessage()),
            $this->redactor->redact($this->logSummary($result)),
            $workingDirectory,
            array_map(static fn (AgentArtifact $artifact): string => $artifact->path(), $result->artifacts()),
            $result->exitCode(),
            $result->tokenUsage(),
        );
        $this->entityManager->flush();

        return $result;
    }

    private function logSummary(AgentRunnerResult $result): string
    {
        return trim(sprintf(
            "stdout: %s\nstderr: %s",
            $result->stdout(),
            $result->stderr(),
        ));
    }
}
