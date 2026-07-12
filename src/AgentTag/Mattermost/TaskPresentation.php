<?php

namespace App\AgentTag\Mattermost;

final readonly class TaskPresentation
{
    public function __construct(
        public string $title,
        public string $acknowledgement,
    ) {
    }
}
