<?php

namespace App\AgentTag\Mattermost;

final readonly class MattermostInteractionResult
{
    private function __construct(
        private string $status,
        private string $message,
    ) {
    }

    public static function ignored(): self
    {
        return new self('ignored', '');
    }

    public static function duplicate(): self
    {
        return new self('duplicate', '');
    }

    public static function handled(string $message): self
    {
        return new self('handled', $message);
    }

    public function isHandled(): bool
    {
        return 'handled' === $this->status;
    }

    public function message(): string
    {
        return $this->message;
    }
}
