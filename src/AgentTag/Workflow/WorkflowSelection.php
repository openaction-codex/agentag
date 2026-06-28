<?php

namespace App\AgentTag\Workflow;

final readonly class WorkflowSelection
{
    private function __construct(
        private ?WorkflowDefinition $workflow,
        private ?string $message,
    ) {
    }

    public static function selected(WorkflowDefinition $workflow): self
    {
        return new self($workflow, null);
    }

    public static function unselected(string $message): self
    {
        return new self(null, $message);
    }

    public function isSelected(): bool
    {
        return null !== $this->workflow;
    }

    public function workflow(): WorkflowDefinition
    {
        if (null === $this->workflow) {
            throw new \LogicException('No workflow was selected.');
        }

        return $this->workflow;
    }

    public function message(): string
    {
        if (null === $this->message) {
            throw new \LogicException('Selected workflow does not have a fallback message.');
        }

        return $this->message;
    }
}
