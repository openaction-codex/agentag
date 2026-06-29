<?php

namespace App\AgentTag\Workspace;

interface WorkspaceRevisionResolver
{
    public function revisionFor(string $workspaceDirectory): ?string;
}
