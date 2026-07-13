<?php

namespace App\AgentTag\Runner;

final readonly class AgentRunnerProgress
{
    /**
     * @param array<string, string|bool> $context
     */
    public function __construct(
        private string $type,
        private string $message,
        private array $context = [],
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

    /** @return array<string, string|bool> */
    public function context(): array
    {
        return $this->context;
    }
}
