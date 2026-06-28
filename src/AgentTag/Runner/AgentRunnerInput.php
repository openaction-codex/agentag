<?php

namespace App\AgentTag\Runner;

final readonly class AgentRunnerInput
{
    /**
     * @param array<string, string> $environment
     */
    public function __construct(
        private string $prompt,
        private string $workingDirectory,
        private string $artifactsDirectory,
        private array $environment,
        private int $timeoutSeconds,
        private string $runnerMode,
    ) {
    }

    public function prompt(): string
    {
        return $this->prompt;
    }

    public function workingDirectory(): string
    {
        return $this->workingDirectory;
    }

    public function artifactsDirectory(): string
    {
        return $this->artifactsDirectory;
    }

    /**
     * @return array<string, string>
     */
    public function environment(): array
    {
        return $this->environment;
    }

    public function timeoutSeconds(): int
    {
        return $this->timeoutSeconds;
    }

    public function runnerMode(): string
    {
        return $this->runnerMode;
    }
}
