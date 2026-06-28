<?php

namespace App\AgentTag\Codebase;

use App\AgentTag\Configuration\ConfiguredRepository;

final readonly class RepositoryClone
{
    public function __construct(
        private ConfiguredRepository $repository,
        private string $path,
        private string $baseRef = 'HEAD',
        private ?string $createdBranch = null,
    ) {
    }

    public function repository(): ConfiguredRepository
    {
        return $this->repository;
    }

    public function path(): string
    {
        return $this->path;
    }

    public function baseRef(): string
    {
        return $this->baseRef;
    }

    public function createdBranch(): ?string
    {
        return $this->createdBranch;
    }
}
