<?php

namespace App\AgentTag\Session;

use App\AgentTag\Chat\ChatSessionReference;
use App\AgentTag\Workflow\WorkflowDefinition;

interface ChatSessionStore
{
    public function recordRun(
        ChatSessionReference $reference,
        string $inputSummary,
        ChatThreadContext $threadContext,
        WorkflowDefinition $workflow,
    ): void;
}
