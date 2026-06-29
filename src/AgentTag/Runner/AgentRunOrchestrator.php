<?php

namespace App\AgentTag\Runner;

use App\AgentTag\Run\RunEventRecorder;
use App\AgentTag\Security\SensitiveTextRedactor;
use App\AgentTag\Workspace\WorkspaceLayout;
use App\Entity\AgentRun;
use App\Entity\RunEvent;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

final readonly class AgentRunOrchestrator
{
    public function __construct(
        private AgentRunnerInterface $runner,
        private WorkspaceLayout $workspaceLayout,
        private SensitiveTextRedactor $redactor,
        private EntityManagerInterface $entityManager,
        private ?RunEventRecorder $runEventRecorder = null,
        private ?LoggerInterface $logger = null,
    ) {
    }

    /**
     * @param array<string, string> $environment
     */
    public function run(
        AgentRun $run,
        string $runIdentifier,
        string $prompt,
        string $runnerMode,
        int $timeoutSeconds,
        array $environment = [],
        ?AgentRunnerProgressSink $progressSink = null,
    ): AgentRunnerResult {
        $workingDirectory = $run->workspacePath() ?? $this->workspaceLayout->runPath($runIdentifier);
        $artifactsDirectory = $this->workspaceLayout->artifactsPath($runIdentifier);

        if (!is_dir($workingDirectory)) {
            mkdir($workingDirectory, 0777, true);
        }
        if (!is_dir($artifactsDirectory)) {
            mkdir($artifactsDirectory, 0777, true);
        }

        $this->runEventRecorder?->record($run, RunEvent::TYPE_WORKSPACE_PREPARED, 'Prepared run workspace.', [
            'working_directory' => $workingDirectory,
            'artifacts_directory' => $artifactsDirectory,
        ]);
        if ($run->interruptionRequested()) {
            $run->markInterrupted('Run interrupted before the agent runner started.', $workingDirectory);
            $this->entityManager->flush();
            $this->runEventRecorder?->record($run, RunEvent::TYPE_RUNNER_FINISHED, 'Agent runner skipped because interruption was requested.', [
                'status' => $run->status(),
                'exit_code' => $run->exitCode(),
                'workspace_path' => $workingDirectory,
            ]);

            return new AgentRunnerResult(130, '', '', '', [], null);
        }

        $run->markRunning();
        $this->entityManager->flush();
        $this->runEventRecorder?->record($run, RunEvent::TYPE_RUNNER_STARTED, 'Started agent runner.', [
            'runner_mode' => $runnerMode,
            'timeout_seconds' => $timeoutSeconds,
        ]);
        $this->logger?->info('Starting agent runner.', [
            'run_id' => $run->id(),
            'working_directory' => $workingDirectory,
            'runner_mode' => $runnerMode,
        ]);

        $result = $this->runner->run(new AgentRunnerInput(
            $prompt,
            $workingDirectory,
            $artifactsDirectory,
            $environment,
            $timeoutSeconds,
            $runnerMode,
            $progressSink,
            fn (): bool => $this->interruptionRequested($run),
        ));

        $status = $run->interruptionRequested() ? AgentRun::STATUS_INTERRUPTED : ($result->successful() ? AgentRun::STATUS_COMPLETED : AgentRun::STATUS_FAILED);
        $summary = AgentRun::STATUS_INTERRUPTED === $status ? 'Run interrupted by a newer message in this thread.' : $result->finalMessage();
        $run->recordRunnerResult(
            $status,
            $this->redactor->redact($summary),
            $this->redactor->redact($this->logSummary($result)),
            $workingDirectory,
            array_map(static fn (AgentArtifact $artifact): string => $artifact->path(), $result->artifacts()),
            $result->exitCode(),
            $result->tokenUsage(),
        );
        $this->entityManager->flush();

        $this->runEventRecorder?->record($run, RunEvent::TYPE_RUNNER_FINISHED, $result->successful() ? 'Agent runner completed.' : 'Agent runner failed.', [
            'status' => $run->status(),
            'exit_code' => $result->exitCode(),
            'workspace_path' => $workingDirectory,
        ]);
        if (null !== $result->tokenUsage()) {
            $this->runEventRecorder?->record($run, RunEvent::TYPE_TOKEN_USAGE, 'Recorded token usage.', [
                'input_tokens' => $result->tokenUsage()->inputTokens(),
                'output_tokens' => $result->tokenUsage()->outputTokens(),
                'total_tokens' => $result->tokenUsage()->totalTokens(),
            ]);
        }
        $this->logger?->info('Finished agent runner.', [
            'run_id' => $run->id(),
            'status' => $run->status(),
            'exit_code' => $run->exitCode(),
            'input_tokens' => $run->inputTokens(),
            'output_tokens' => $run->outputTokens(),
            'total_tokens' => $run->totalTokens(),
        ]);

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

    private function interruptionRequested(AgentRun $run): bool
    {
        $this->entityManager->refresh($run);

        return $run->interruptionRequested();
    }
}
