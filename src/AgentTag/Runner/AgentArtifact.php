<?php

namespace App\AgentTag\Runner;

final readonly class AgentArtifact
{
    public function __construct(
        private string $path,
        private string $label,
        private int $size = 0,
        private string $sha256 = '',
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

    public function size(): int
    {
        return $this->size;
    }

    public function sha256(): string
    {
        return $this->sha256;
    }

    /** @return array{path: string, name: string, size: int, sha256: string} */
    public function metadata(): array
    {
        return [
            'path' => $this->path,
            'name' => $this->label,
            'size' => $this->size,
            'sha256' => $this->sha256,
        ];
    }
}
