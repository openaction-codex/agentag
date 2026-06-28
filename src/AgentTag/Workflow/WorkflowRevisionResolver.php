<?php

namespace App\AgentTag\Workflow;

interface WorkflowRevisionResolver
{
    public function revisionFor(string $workflowDirectory): ?string;
}
