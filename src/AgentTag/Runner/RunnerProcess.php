<?php

namespace App\AgentTag\Runner;

interface RunnerProcess
{
    /**
     * @param callable(string, string): void|null $callback
     */
    public function run(?callable $callback = null): int;

    public function exitCode(): int;

    public function output(): string;

    public function errorOutput(): string;
}
