<?php

namespace App\AgentTag\Workflow;

use Symfony\Component\Process\Process;

final readonly class GitWorkflowRevisionResolver implements WorkflowRevisionResolver
{
    #[\Override]
    public function revisionFor(string $workflowDirectory): ?string
    {
        if (!is_dir($workflowDirectory.'/.git')) {
            return null;
        }

        $process = new Process(['git', '-C', $workflowDirectory, 'rev-parse', '--short', 'HEAD']);
        $process->run();

        if (!$process->isSuccessful()) {
            return null;
        }

        $revision = trim($process->getOutput());

        return '' === $revision ? null : $revision;
    }
}
