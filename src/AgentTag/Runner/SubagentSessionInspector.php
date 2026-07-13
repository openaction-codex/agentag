<?php

namespace App\AgentTag\Runner;

interface SubagentSessionInspector
{
    public function inspect(string $threadId, string $codexHome): ?SubagentSessionMetadata;

    public function progressSince(string $threadId, string $codexHome, int $offset): SubagentSessionProgress;
}
