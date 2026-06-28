<?php

namespace App\AgentTag\Memory;

final readonly class GlobalMemoryCommandContext
{
    public function __construct(
        private string $platform,
        private string $userId,
        private string $threadId,
        private string $messageId,
    ) {
    }

    public function platform(): string
    {
        return $this->platform;
    }

    public function userId(): string
    {
        return $this->userId;
    }

    public function threadId(): string
    {
        return $this->threadId;
    }

    public function messageId(): string
    {
        return $this->messageId;
    }
}
