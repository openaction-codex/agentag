<?php

namespace App\AgentTag\Runner;

interface RunnerProcess
{
    public function run(): int;

    public function exitCode(): int;

    public function output(): string;

    public function errorOutput(): string;
}
