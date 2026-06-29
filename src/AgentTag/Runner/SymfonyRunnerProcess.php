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
    public function start(?callable $callback = null): void
    {
        $this->process->start($callback);
    }

    #[\Override]
    public function wait(?callable $callback = null): int
    {
        return $this->process->wait($callback);
    }

    #[\Override]
    public function isRunning(): bool
    {
        return $this->process->isRunning();
    }

    #[\Override]
    public function stop(float $timeout = 10.0): int
    {
        return $this->process->stop($timeout) ?? 0;
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
