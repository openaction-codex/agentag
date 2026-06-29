<?php

namespace App\AgentTag\Agent;

use App\AgentTag\Configuration\AgentTagSettings;
use App\AgentTag\Workspace\WorkspaceRevisionResolver;

final readonly class WorkspaceAgentProfileProvider implements AgentProfileProvider
{
    public function __construct(
        private AgentTagSettings $settings,
        private WorkspaceRevisionResolver $revisionResolver,
    ) {
    }

    #[\Override]
    public function profile(): AgentProfile
    {
        $workspacePath = $this->settings->workspacePath();
        if (!is_dir($workspacePath)) {
            throw new \RuntimeException(sprintf('Workspace template directory "%s" does not exist.', $workspacePath));
        }

        return new AgentProfile(
            'agent',
            $workspacePath,
            $this->revisionResolver->revisionFor($workspacePath),
            'codex-full-access',
            $this->settings->runTimeoutSeconds(),
        );
    }
}
