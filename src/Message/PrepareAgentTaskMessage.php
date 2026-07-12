<?php

namespace App\Message;

final readonly class PrepareAgentTaskMessage
{
    public function __construct(private int $runId)
    {
    }

    public function runId(): int
    {
        return $this->runId;
    }
}
