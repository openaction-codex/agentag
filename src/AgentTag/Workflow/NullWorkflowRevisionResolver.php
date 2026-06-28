<?php

namespace App\AgentTag\Workflow;

final readonly class NullWorkflowRevisionResolver implements WorkflowRevisionResolver
{
    #[\Override]
    public function revisionFor(string $workflowDirectory): ?string
    {
        return null;
    }
}
