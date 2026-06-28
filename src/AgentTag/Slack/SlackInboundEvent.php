<?php

namespace App\AgentTag\Slack;

final readonly class SlackInboundEvent
{
    public function __construct(
        private string $eventId,
        private string $text,
        private string $eventTs,
        private string $threadTs,
        private string $channelId,
        private string $teamId,
        private string $userId,
        private string $token,
    ) {
    }

    public function eventId(): string
    {
        return $this->eventId;
    }

    public function text(): string
    {
        return $this->text;
    }

    public function eventTs(): string
    {
        return $this->eventTs;
    }

    public function threadTs(): string
    {
        return $this->threadTs;
    }

    public function channelId(): string
    {
        return $this->channelId;
    }

    public function teamId(): string
    {
        return $this->teamId;
    }

    public function userId(): string
    {
        return $this->userId;
    }

    public function token(): string
    {
        return $this->token;
    }
}
