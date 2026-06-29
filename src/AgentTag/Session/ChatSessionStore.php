<?php

namespace App\AgentTag\Session;

use App\AgentTag\Agent\AgentProfile;
use App\AgentTag\Chat\ChatSessionReference;
use App\Entity\AgentRun;

interface ChatSessionStore
{
    public function recordRun(
        ChatSessionReference $reference,
        string $inputSummary,
        ChatThreadContext $threadContext,
        AgentProfile $agent,
        ?string $sourceEventId = null,
        ?string $requesterId = null,
    ): AgentRun;
}
