<?php

namespace App\AgentTag\Mattermost;

final readonly class TaskCard
{
    /** @param array<string, mixed> $props */
    public function __construct(
        public string $message,
        public array $props,
    ) {
    }
}
