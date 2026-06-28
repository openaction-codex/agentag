<?php

namespace App\AgentTag\Linear;

use App\AgentTag\Workflow\WorkflowDefinition;

final class LinearToolUnavailableException extends \RuntimeException
{
    public static function forWorkflow(WorkflowDefinition $workflow): self
    {
        return new self(sprintf('Linear tool is not enabled for workflow `%s`.', $workflow->name()));
    }
}
