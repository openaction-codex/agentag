<?php

namespace App\AgentTag\Session;

use App\AgentTag\Chat\ChatSessionReference;
use App\AgentTag\Workflow\WorkflowDefinition;
use App\Entity\AgentRun;

interface ChatSessionStore
{
    public function recordRun(
        ChatSessionReference $reference,
        string $inputSummary,
        ChatThreadContext $threadContext,
        WorkflowDefinition $workflow,
        ?string $sourceEventId = null,
        ?string $requesterId = null,
    ): AgentRun;
}
