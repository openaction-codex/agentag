<?php

namespace App\AgentTag\Run;

use App\AgentTag\Chat\ChatSessionReference;
use App\Entity\AgentRun;

interface RunInterrupter
{
    public function cancelActiveRun(ChatSessionReference $reference, string $sourceEventId, string $requesterId): ?AgentRun;

    public function steerActiveRun(ChatSessionReference $reference, string $instruction, string $sourceEventId, string $requesterId): ?AgentRun;

    public function retryLatestRun(ChatSessionReference $reference, string $instruction): ?AgentRun;
}
