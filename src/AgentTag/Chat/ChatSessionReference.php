<?php

namespace App\AgentTag\Chat;

final readonly class ChatSessionReference
{
    public function __construct(
        private string $platform,
        private string $teamId,
        private string $channelId,
        private string $threadId,
    ) {
    }

    public function key(): string
    {
        return implode(':', [$this->platform, $this->teamId, $this->channelId, $this->threadId]);
    }

    public function platform(): string
    {
        return $this->platform;
    }

    public function teamId(): string
    {
        return $this->teamId;
    }

    public function channelId(): string
    {
        return $this->channelId;
    }

    public function threadId(): string
    {
        return $this->threadId;
    }
}
