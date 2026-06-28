<?php

namespace App\AgentTag\Slack;

final readonly class SlackSettings
{
    public function __construct(
        private bool $enabled,
        private string $verificationToken,
    ) {
    }

    public function enabled(): bool
    {
        return $this->enabled;
    }

    public function verificationToken(): string
    {
        return $this->verificationToken;
    }
}
