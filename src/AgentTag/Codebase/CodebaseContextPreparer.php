<?php

namespace App\AgentTag\Codebase;

use App\AgentTag\Workflow\WorkflowDefinition;

final readonly class CodebaseContextPreparer
{
    public function __construct(
        private RepositoryResolver $repositoryResolver,
        private GitRepositoryCloner $repositoryCloner,
    ) {
    }

    public function prepare(WorkflowDefinition $workflow, string $runIdentifier): CodebaseContext
    {
        $clones = [];
        foreach ($this->repositoryResolver->repositoriesFor($workflow) as $repository) {
            $clones[] = $this->repositoryCloner->cloneForRun($repository, $runIdentifier);
        }

        return new CodebaseContext($clones);
    }
}
