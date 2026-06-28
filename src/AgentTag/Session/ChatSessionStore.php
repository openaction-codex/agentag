<?php

namespace App\AgentTag\Session;

use App\AgentTag\Chat\ChatSessionReference;

interface ChatSessionStore
{
    public function recordRun(ChatSessionReference $reference, string $inputSummary, ChatThreadContext $threadContext): void;
}
