<?php

namespace App\AgentTag\Runner;

final readonly class CodexCliRunner implements AgentRunnerInterface
{
    public function __construct(
        private ProcessFactory $processFactory,
        private string $model = 'gpt-5.6-luna',
        private string $reasoningEffort = 'max',
        private ?SubagentSessionInspector $subagentSessionInspector = null,
    ) {
        if ('' === trim($this->model)) {
            throw new \InvalidArgumentException('Codex task model must not be blank.');
        }
        if (!in_array($this->reasoningEffort, ['none', 'minimal', 'low', 'medium', 'high', 'xhigh', 'max', 'ultra'], true)) {
            throw new \InvalidArgumentException('Codex task reasoning effort is invalid.');
        }
    }

    #[\Override]
    public function run(AgentRunnerInput $input): AgentRunnerResult
    {
        if (!is_dir($input->artifactsDirectory())) {
            mkdir($input->artifactsDirectory(), 0777, true);
        }

        $lastMessagePath = $input->artifactsDirectory().'/codex-last-message.txt';
        $command = null === $input->resumeSessionId()
            ? [
                'codex', 'exec',
                '--dangerously-bypass-approvals-and-sandbox',
                '--skip-git-repo-check',
                '--json',
                '--model', $this->model,
                '-c', sprintf('model_reasoning_effort="%s"', $this->reasoningEffort),
                '--cd', $input->workingDirectory(),
                '--output-last-message', $lastMessagePath,
                '-',
            ]
            : [
                'codex', 'exec', 'resume',
                '--dangerously-bypass-approvals-and-sandbox',
                '--skip-git-repo-check',
                '--json',
                '--model', $this->model,
                '-c', sprintf('model_reasoning_effort="%s"', $this->reasoningEffort),
                '--output-last-message', $lastMessagePath,
                $input->resumeSessionId(),
                '-',
            ];

        $process = $this->processFactory->create(
            $command,
            $input->workingDirectory(),
            $input->environment(),
            $input->prompt(),
            $input->timeoutSeconds(),
        );
        $parser = new CodexJsonEventParser();
        $interrupted = false;
        $reportedSessionId = $input->resumeSessionId();
        /** @var array<string, true> $pendingSubagentThreadIds */
        $pendingSubagentThreadIds = [];
        /** @var array<string, int> $subagentProgressOffsets */
        $subagentProgressOffsets = [];
        $lastSubagentProgressPollAt = 0.0;
        $callback = function (string $type, string $buffer) use ($input, $parser, &$reportedSessionId, &$pendingSubagentThreadIds, &$subagentProgressOffsets, &$lastSubagentProgressPollAt): void {
            if ('out' !== $type && !str_ends_with($type, 'OUT')) {
                return;
            }

            foreach ($parser->consume($buffer) as $progress) {
                $this->deliverProgress($progress, $input, $pendingSubagentThreadIds, $subagentProgressOffsets);
            }
            $this->verifyPendingSubagents($input, $pendingSubagentThreadIds);
            $this->publishSubagentProgress($input, $subagentProgressOffsets, $lastSubagentProgressPollAt);
            if (null !== $parser->threadId() && $parser->threadId() !== $reportedSessionId) {
                $reportedSessionId = $parser->threadId();
                $input->sessionStarted($reportedSessionId);
            }
        };

        $input->progressSink()?->onHeartbeat();
        $process->start($callback);
        while ($process->isRunning()) {
            $input->progressSink()?->onHeartbeat();
            $this->verifyPendingSubagents($input, $pendingSubagentThreadIds);
            $this->publishSubagentProgress($input, $subagentProgressOffsets, $lastSubagentProgressPollAt);
            if ($input->interruptionRequested()) {
                $interrupted = true;
                $process->stop(5.0);
                break;
            }

            usleep(250000);
        }
        $process->wait($callback);

        foreach ($parser->flush() as $progress) {
            $this->deliverProgress($progress, $input, $pendingSubagentThreadIds, $subagentProgressOffsets);
        }
        $this->verifyPendingSubagents($input, $pendingSubagentThreadIds);
        $this->publishSubagentProgress($input, $subagentProgressOffsets, $lastSubagentProgressPollAt, true);

        $stdout = $process->output();
        $stderr = $process->errorOutput();
        $exitCode = $process->exitCode();
        if ($interrupted) {
            return new AgentRunnerResult(
                130,
                'Run interrupted by a newer message in this thread.',
                $stdout,
                $stderr,
                [],
                $this->tokenUsageFromOutput($stdout),
                $parser->threadId() ?? $input->resumeSessionId(),
            );
        }

        $finalMessage = $this->finalMessage($lastMessagePath, $stdout, $exitCode, $parser);
        $parsed = (new TaskContinuationParser())->parse($finalMessage);

        return new AgentRunnerResult(
            $exitCode,
            $parsed['message'],
            $stdout,
            $stderr,
            [new AgentArtifact($lastMessagePath, 'Codex final message')],
            $this->tokenUsageFromOutput($stdout),
            $parser->threadId() ?? $input->resumeSessionId(),
            $parsed['continuation'],
        );
    }

    /**
     * @param array<string, true> $pendingSubagentThreadIds
     * @param array<string, int>  $subagentProgressOffsets
     */
    private function deliverProgress(AgentRunnerProgress $progress, AgentRunnerInput $input, array &$pendingSubagentThreadIds, array &$subagentProgressOffsets): void
    {
        $progress = $this->enrichSubagentProgress($progress, $input);
        if ('subagent_started' === $progress->type()) {
            $threadId = $progress->context()['thread_id'] ?? null;
            if (is_string($threadId)) {
                $subagentProgressOffsets[$threadId] ??= 0;
                if (true === ($progress->context()['verified'] ?? false)) {
                    unset($pendingSubagentThreadIds[$threadId]);
                } else {
                    $pendingSubagentThreadIds[$threadId] = true;
                }
            }
        }
        $input->progressSink()?->onProgress($progress);
    }

    /** @param array<string, int> $subagentProgressOffsets */
    private function publishSubagentProgress(AgentRunnerInput $input, array &$subagentProgressOffsets, float &$lastPollAt, bool $force = false): void
    {
        if (null === $this->subagentSessionInspector || null === $input->progressSink() || [] === $subagentProgressOffsets) {
            return;
        }

        $now = microtime(true);
        if (!$force && 0.0 !== $lastPollAt && $now - $lastPollAt < 1.0) {
            return;
        }
        $lastPollAt = $now;

        foreach ($subagentProgressOffsets as $threadId => $offset) {
            $progress = $this->subagentSessionInspector->progressSince($threadId, $this->codexHome($input), $offset);
            $subagentProgressOffsets[$threadId] = $progress->nextOffset;
            foreach ($progress->messages as $message) {
                $input->progressSink()->onProgress(new AgentRunnerProgress('subagent_progress', $message, [
                    'thread_id' => $threadId,
                ]));
            }
        }
    }

    /** @param array<string, true> $pendingSubagentThreadIds */
    private function verifyPendingSubagents(AgentRunnerInput $input, array &$pendingSubagentThreadIds): void
    {
        if (null === $this->subagentSessionInspector || null === $input->progressSink()) {
            return;
        }

        foreach (array_keys($pendingSubagentThreadIds) as $threadId) {
            $metadata = $this->subagentSessionInspector->inspect($threadId, $this->codexHome($input));
            if (null === $metadata) {
                continue;
            }

            $input->progressSink()->onProgress(new AgentRunnerProgress('subagent_started', 'Codex verified the subagent session.', [
                'thread_id' => $metadata->threadId,
                'agent' => $metadata->agent,
                'model' => $metadata->model,
                'reasoning_effort' => $metadata->reasoningEffort,
                'verified' => true,
            ]));
            unset($pendingSubagentThreadIds[$threadId]);
        }
    }

    private function enrichSubagentProgress(AgentRunnerProgress $progress, AgentRunnerInput $input): AgentRunnerProgress
    {
        if ('subagent_started' !== $progress->type()) {
            return $progress;
        }

        $threadId = $progress->context()['thread_id'] ?? null;
        if (!is_string($threadId) || null === $this->subagentSessionInspector) {
            return $progress;
        }

        $metadata = $this->subagentSessionInspector->inspect($threadId, $this->codexHome($input));
        if (null === $metadata) {
            return new AgentRunnerProgress($progress->type(), $progress->message(), [
                'thread_id' => $threadId,
                'verified' => false,
            ]);
        }

        return new AgentRunnerProgress($progress->type(), $progress->message(), [
            'thread_id' => $metadata->threadId,
            'agent' => $metadata->agent,
            'model' => $metadata->model,
            'reasoning_effort' => $metadata->reasoningEffort,
            'verified' => true,
        ]);
    }

    private function codexHome(AgentRunnerInput $input): string
    {
        $codexHome = $input->environment()['CODEX_HOME'] ?? getenv('CODEX_HOME');
        if (is_string($codexHome) && '' !== trim($codexHome)) {
            return rtrim($codexHome, '/');
        }

        $home = getenv('HOME');

        return is_string($home) && '' !== trim($home) ? rtrim($home, '/').'/.codex' : '.codex';
    }

    private function finalMessage(string $lastMessagePath, string $stdout, int $exitCode, CodexJsonEventParser $parser): string
    {
        if (is_file($lastMessagePath)) {
            $message = trim((string) file_get_contents($lastMessagePath));
            if ('' !== $message) {
                return $message;
            }
        }

        $message = $parser->lastAgentMessageFromOutput($stdout);
        if (null !== $message) {
            return $message;
        }

        $stdout = trim($stdout);
        if ('' === $stdout) {
            return 0 === $exitCode
                ? 'Run completed, but Codex did not provide a final message.'
                : 'Run failed before Codex produced a final message.';
        }

        if ($this->looksLikeJsonEventOutput($stdout)) {
            return 0 === $exitCode
                ? 'Run completed, but Codex did not provide a final message.'
                : 'Run failed before Codex produced a final message.';
        }

        return $stdout;
    }

    private function looksLikeJsonEventOutput(string $stdout): bool
    {
        foreach (explode("\n", $stdout) as $line) {
            $line = trim($line);
            if ('' === $line) {
                continue;
            }

            try {
                $data = json_decode($line, true, flags: \JSON_THROW_ON_ERROR);
            } catch (\JsonException) {
                return false;
            }

            if (!is_array($data) || (!isset($data['type']) && !isset($data['item']) && !isset($data['usage']) && !isset($data['token_usage']))) {
                return false;
            }
        }

        return true;
    }

    private function tokenUsageFromOutput(string $stdout): ?TokenUsage
    {
        foreach (explode("\n", $stdout) as $line) {
            $data = json_decode($line, true);
            if (!is_array($data)) {
                continue;
            }

            $usage = $data['token_usage'] ?? $data['usage'] ?? null;
            if (!is_array($usage)) {
                continue;
            }

            $inputTokens = $usage['input_tokens'] ?? $usage['prompt_tokens'] ?? null;
            $outputTokens = $usage['output_tokens'] ?? $usage['completion_tokens'] ?? null;
            if (is_int($inputTokens) && is_int($outputTokens)) {
                return new TokenUsage($inputTokens, $outputTokens);
            }
        }

        return null;
    }
}
