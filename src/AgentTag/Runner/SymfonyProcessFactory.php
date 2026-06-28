<?php

namespace App\AgentTag\Runner;

use Symfony\Component\Process\Process;

final readonly class SymfonyProcessFactory implements ProcessFactory
{
    #[\Override]
    public function create(array $command, string $workingDirectory, array $environment, string $input, int $timeoutSeconds): RunnerProcess
    {
        $process = new Process($command, $workingDirectory, $environment, $input, $timeoutSeconds);

        return new SymfonyRunnerProcess($process);
    }
}
