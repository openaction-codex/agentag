<?php

namespace App\AgentTag\Runner;

interface RunnerProcess
{
    /**
     * @param callable(string, string): void|null $callback
     */
    public function run(?callable $callback = null): int;

    /**
     * @param callable(string, string): void|null $callback
     */
    public function start(?callable $callback = null): void;

    /**
     * @param callable(string, string): void|null $callback
     */
    public function wait(?callable $callback = null): int;

    public function isRunning(): bool;

    public function stop(float $timeout = 10.0): int;

    public function exitCode(): int;

    public function output(): string;

    public function errorOutput(): string;
}
