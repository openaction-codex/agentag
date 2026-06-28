<?php

namespace App\AgentTag\Linear;

use App\AgentTag\Tool\ToolDefinition;
use App\Entity\ApprovalRequest;

final readonly class LinearToolInstruction
{
    /**
     * @param array<string, mixed> $payload
     */
    public function __construct(
        private ToolDefinition $tool,
        private string $operation,
        private ?string $targetIssueIdentifier,
        private array $payload,
        private bool $confirmationRequired,
        private ?ApprovalRequest $approvalRequest,
    ) {
    }

    public function tool(): ToolDefinition
    {
        return $this->tool;
    }

    public function operation(): string
    {
        return $this->operation;
    }

    public function targetIssueIdentifier(): ?string
    {
        return $this->targetIssueIdentifier;
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        return $this->payload;
    }

    public function confirmationRequired(): bool
    {
        return $this->confirmationRequired;
    }

    public function approvalRequest(): ?ApprovalRequest
    {
        return $this->approvalRequest;
    }

    public function runnerInstruction(): string
    {
        $transport = ToolDefinition::TYPE_MCP === $this->tool->type()
            ? sprintf('MCP server `%s`', $this->tool->server() ?? '')
            : sprintf('CLI command `%s`', $this->tool->command() ?? '');
        $target = null === $this->targetIssueIdentifier ? '' : sprintf(' for `%s`', $this->targetIssueIdentifier);

        return sprintf(
            'Use configured Linear tool `%s` via %s to run `%s`%s.',
            $this->tool->name(),
            $transport,
            $this->operation,
            $target,
        );
    }
}
