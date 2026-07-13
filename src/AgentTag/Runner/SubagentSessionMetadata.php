<?php

namespace App\AgentTag\Runner;

final readonly class SubagentSessionMetadata
{
    public function __construct(
        public string $threadId,
        public string $agent,
        public string $model,
        public string $reasoningEffort,
    ) {
    }
}
