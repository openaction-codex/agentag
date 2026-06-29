<?php

namespace App\AgentTag\Runner;

final readonly class AgentRunnerProgress
{
    public function __construct(
        private string $type,
        private string $message,
    ) {
    }

    public function type(): string
    {
        return $this->type;
    }

    public function message(): string
    {
        return $this->message;
    }
}
