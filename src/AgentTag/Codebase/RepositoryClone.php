<?php

namespace App\AgentTag\Codebase;

use App\AgentTag\Configuration\ConfiguredRepository;

final readonly class RepositoryClone
{
    public function __construct(
        private ConfiguredRepository $repository,
        private string $path,
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
}
