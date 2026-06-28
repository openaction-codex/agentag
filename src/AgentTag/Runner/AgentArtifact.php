<?php

namespace App\AgentTag\Runner;

final readonly class AgentArtifact
{
    public function __construct(
        private string $path,
        private string $label,
    ) {
    }

    public function path(): string
    {
        return $this->path;
    }

    public function label(): string
    {
        return $this->label;
    }
}
