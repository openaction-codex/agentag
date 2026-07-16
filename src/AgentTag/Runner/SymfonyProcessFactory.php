<?php

namespace App\AgentTag\Runner;

use Symfony\Component\Process\Process;

final readonly class SymfonyProcessFactory implements ProcessFactory
{
    public function __construct(private string $githubPatToken = '')
    {
    }

    #[\Override]
    public function create(array $command, string $workingDirectory, array $environment, string $input, int $timeoutSeconds): RunnerProcess
    {
        if ('' !== $this->githubPatToken) {
            $environment['GITHUB_PAT_TOKEN'] ??= $this->githubPatToken;
        }

        $process = new Process($command, $workingDirectory, $environment, $input, $timeoutSeconds);

        return new SymfonyRunnerProcess($process);
    }
}
