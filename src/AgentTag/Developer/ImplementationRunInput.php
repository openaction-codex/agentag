<?php

namespace App\AgentTag\Developer;

use App\AgentTag\Codebase\RepositoryClone;
use App\AgentTag\Session\ChatThreadContext;

final readonly class ImplementationRunInput
{
    /**
     * @param list<string> $checkCommands
     */
    public function __construct(
        private string $technicalSpec,
        private RepositoryClone $repositoryClone,
        private string $branchName,
        private array $checkCommands,
        private ?ChatThreadContext $sessionContext = null,
    ) {
    }

    public function technicalSpec(): string
    {
        return $this->technicalSpec;
    }

    public function repositoryClone(): RepositoryClone
    {
        return $this->repositoryClone;
    }

    public function branchName(): string
    {
        return $this->branchName;
    }

    /**
     * @return list<string>
     */
    public function checkCommands(): array
    {
        return $this->checkCommands;
    }

    public function sessionContext(): ?ChatThreadContext
    {
        return $this->sessionContext;
    }
}
