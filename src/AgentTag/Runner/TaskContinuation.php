<?php

namespace App\AgentTag\Runner;

final readonly class TaskContinuation
{
    public function __construct(
        private int $delaySeconds,
        private string $reason,
    ) {
    }

    public function delaySeconds(): int
    {
        return $this->delaySeconds;
    }

    public function reason(): string
    {
        return $this->reason;
    }
}
