<?php

namespace App\AgentTag\Product;

use App\AgentTag\Codebase\CodebaseContext;
use App\AgentTag\Session\ChatThreadContext;

final readonly class ProductSpecInput
{
    public function __construct(
        private string $inlineText = '',
        private ?ChatThreadContext $threadContext = null,
        private ?CodebaseContext $codebaseContext = null,
        private ?string $linearIssueIdentifier = null,
    ) {
    }

    public function inlineText(): string
    {
        return $this->inlineText;
    }

    public function threadContext(): ?ChatThreadContext
    {
        return $this->threadContext;
    }

    public function codebaseContext(): ?CodebaseContext
    {
        return $this->codebaseContext;
    }

    public function linearIssueIdentifier(): ?string
    {
        return $this->linearIssueIdentifier;
    }
}
