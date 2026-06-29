<?php

namespace App\AgentTag\Runner;

final readonly class CodexCliRunner implements AgentRunnerInterface
{
    public function __construct(private ProcessFactory $processFactory)
    {
    }

    #[\Override]
    public function run(AgentRunnerInput $input): AgentRunnerResult
    {
        if (!is_dir($input->artifactsDirectory())) {
            mkdir($input->artifactsDirectory(), 0777, true);
        }

        $lastMessagePath = $input->artifactsDirectory().'/codex-last-message.txt';
        $command = [
            'codex',
            'exec',
            '--dangerously-bypass-approvals-and-sandbox',
            '--skip-git-repo-check',
            '--json',
            '--cd',
            $input->workingDirectory(),
            '--output-last-message',
            $lastMessagePath,
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
        $callback = function (string $type, string $buffer) use ($input, $parser): void {
            if ('out' !== $type && !str_ends_with($type, 'OUT')) {
                return;
            }

            foreach ($parser->consume($buffer) as $progress) {
                $input->progressSink()?->onProgress($progress);
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
        if ($interrupted) {
            return new AgentRunnerResult(
                130,
                'Run interrupted by a newer message in this thread.',
                $stdout,
                $stderr,
                [],
                $this->tokenUsageFromOutput($stdout),
            );
        }

        $finalMessage = is_file($lastMessagePath) ? trim((string) file_get_contents($lastMessagePath)) : trim($stdout);

        return new AgentRunnerResult(
            $process->exitCode(),
            $finalMessage,
            $stdout,
            $stderr,
            [new AgentArtifact($lastMessagePath, 'Codex final message')],
            $this->tokenUsageFromOutput($stdout),
        );
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
