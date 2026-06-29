<?php

namespace App\AgentTag\Runner;

interface AgentRunnerProgressSink
{
    public function onProgress(AgentRunnerProgress $progress): void;

    public function onHeartbeat(): void;
}
