<?php

namespace App\AgentTag\Runner;

interface SubagentSessionInspector
{
    public function inspect(string $threadId, string $codexHome): ?SubagentSessionMetadata;
}
