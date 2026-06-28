<?php

namespace App\AgentTag\Workflow;

interface WorkflowSelector
{
    public function select(string $message): WorkflowSelection;
}
