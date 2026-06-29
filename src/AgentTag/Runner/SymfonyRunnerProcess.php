<?php

namespace App\AgentTag\Runner;

use Symfony\Component\Process\Process;

final readonly class SymfonyRunnerProcess implements RunnerProcess
{
    public function __construct(private Process $process)
    {
    }

    #[\Override]
    public function run(?callable $callback = null): int
    {
        return $this->process->run($callback);
    }

    #[\Override]
    public function exitCode(): int
    {
        return $this->process->getExitCode() ?? 1;
    }

    #[\Override]
    public function output(): string
    {
        return $this->process->getOutput();
    }

    #[\Override]
    public function errorOutput(): string
    {
        return $this->process->getErrorOutput();
    }
}
