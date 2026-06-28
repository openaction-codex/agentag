<?php

namespace App\AgentTag\Runner;

interface AgentRunnerInterface
{
    public function run(AgentRunnerInput $input): AgentRunnerResult;
}
