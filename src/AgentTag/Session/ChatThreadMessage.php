<?php

namespace App\AgentTag\Session;

final readonly class ChatThreadMessage
{
    public function __construct(
        private string $externalId,
        private string $authorId,
        private string $text,
    ) {
    }

    public function externalId(): string
    {
        return $this->externalId;
    }

    public function authorId(): string
    {
        return $this->authorId;
    }

    public function text(): string
    {
        return $this->text;
    }
}
