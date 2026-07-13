<?php

namespace App\AgentTag\Runner;

final readonly class SubagentSessionProgress
{
    /**
     * @param list<string> $messages
     */
    public function __construct(
        public int $nextOffset,
        public array $messages,
    ) {
    }
}
