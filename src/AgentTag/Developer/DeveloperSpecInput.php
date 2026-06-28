<?php

namespace App\AgentTag\Developer;

final readonly class DeveloperSpecInput
{
    public function __construct(
        private string $inlineFunctionalSpec = '',
        private ?string $linearIssueIdentifier = null,
    ) {
    }

    public function inlineFunctionalSpec(): string
    {
        return $this->inlineFunctionalSpec;
    }

    public function linearIssueIdentifier(): ?string
    {
        return $this->linearIssueIdentifier;
    }
}
