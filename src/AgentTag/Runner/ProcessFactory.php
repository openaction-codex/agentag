<?php

namespace App\AgentTag\Runner;

interface ProcessFactory
{
    /**
     * @param list<string>          $command
     * @param array<string, string> $environment
     */
    public function create(array $command, string $workingDirectory, array $environment, string $input, int $timeoutSeconds): RunnerProcess;
}
