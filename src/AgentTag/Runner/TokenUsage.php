<?php

namespace App\AgentTag\Runner;

final readonly class TokenUsage
{
    public function __construct(
        private int $inputTokens,
        private int $outputTokens,
    ) {
        if ($inputTokens < 0 || $outputTokens < 0) {
            throw new \InvalidArgumentException('Token usage cannot be negative.');
        }
    }

    public function inputTokens(): int
    {
        return $this->inputTokens;
    }

    public function outputTokens(): int
    {
        return $this->outputTokens;
    }

    public function totalTokens(): int
    {
        return $this->inputTokens + $this->outputTokens;
    }
}
