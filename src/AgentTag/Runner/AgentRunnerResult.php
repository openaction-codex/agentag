<?php

namespace App\AgentTag\Runner;

final readonly class AgentRunnerResult
{
    /** @param list<AgentArtifact> $artifacts */
    public function __construct(
        private int $exitCode,
        private string $finalMessage,
        private string $stdout,
        private string $stderr,
        private array $artifacts,
        private ?TokenUsage $tokenUsage,
        private ?string $sessionId = null,
        private ?TaskContinuation $continuation = null,
    ) {
    }

    public function successful(): bool
    {
        return 0 === $this->exitCode;
    }

    public function exitCode(): int
    {
        return $this->exitCode;
    }

    public function finalMessage(): string
    {
        return $this->finalMessage;
    }

    public function stdout(): string
    {
        return $this->stdout;
    }

    public function stderr(): string
    {
        return $this->stderr;
    }

    /** @return list<AgentArtifact> */
    public function artifacts(): array
    {
        return $this->artifacts;
    }

    public function tokenUsage(): ?TokenUsage
    {
        return $this->tokenUsage;
    }

    public function sessionId(): ?string
    {
        return $this->sessionId;
    }

    public function continuation(): ?TaskContinuation
    {
        return $this->continuation;
    }
}
