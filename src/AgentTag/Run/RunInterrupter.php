<?php

namespace App\AgentTag\Run;

use App\AgentTag\Chat\ChatSessionReference;

interface RunInterrupter
{
    public function interruptActiveRuns(ChatSessionReference $reference, string $sourceEventId, string $requesterId): int;
}
