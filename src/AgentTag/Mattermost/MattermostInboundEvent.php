<?php

namespace App\AgentTag\Mattermost;

final readonly class MattermostInboundEvent
{
    public function __construct(
        private string $eventId,
        private string $text,
        private string $postId,
        private string $rootId,
        private string $channelId,
        private string $channelType,
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

    public function postId(): string
    {
        return $this->postId;
    }

    public function rootId(): string
    {
        return $this->rootId;
    }

    public function channelId(): string
    {
        return $this->channelId;
    }

    public function channelType(): string
    {
        return $this->channelType;
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
