<?php

namespace App\AgentTag\Runner;

final readonly class CodexCliRunner implements AgentRunnerInterface
{
    public function __construct(
        private ProcessFactory $processFactory,
        private string $model = 'gpt-5.6-luna',
        private string $reasoningEffort = 'xhigh',
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
        $callback = function (string $type, string $buffer) use ($input, $parser, &$reportedSessionId): void {
            if ('out' !== $type && !str_ends_with($type, 'OUT')) {
                return;
            }

            foreach ($parser->consume($buffer) as $progress) {
                $input->progressSink()?->onProgress($progress);
            }
            if (null !== $parser->threadId() && $parser->threadId() !== $reportedSessionId) {
                $reportedSessionId = $parser->threadId();
                $input->sessionStarted($reportedSessionId);
            }
        };

        $input->progressSink()?->onHeartbeat();
        $process->start($callback);
        while ($process->isRunning()) {
            $input->progressSink()?->onHeartbeat();
            if ($input->interruptionRequested()) {
                $interrupted = true;
                $process->stop(5.0);
                break;
            }

            usleep(250000);
        }
        $process->wait($callback);

        foreach ($parser->flush() as $progress) {
            $input->progressSink()?->onProgress($progress);
        }

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
