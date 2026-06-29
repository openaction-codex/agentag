<?php

namespace App\AgentTag\Codebase;

use App\Entity\AgentRun;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

final readonly class CodebaseContextPreparer
{
    public function __construct(
        private RepositoryResolver $repositoryResolver,
        private GitRepositoryCloner $repositoryCloner,
        private ?EntityManagerInterface $entityManager = null,
        private ?LoggerInterface $logger = null,
    ) {
    }

    public function prepare(string $workspacePath, ?AgentRun $run = null): CodebaseContext
    {
        $clones = [];
        foreach ($this->repositoryResolver->repositories() as $repository) {
            $clones[] = $this->repositoryCloner->cloneForWorkspace($repository, $workspacePath);
        }

        $context = new CodebaseContext($clones);
        if (null !== $run) {
            $run->recordRepositoryClones($context->cloneMap(), $context->baseRefMap(), $context->branchMap());
            $this->entityManager?->flush();
            $this->logger?->info('Recorded repository clones for run.', [
                'run_id' => $run->id(),
                'repositories' => array_keys($context->cloneMap()),
            ]);
        }

        return $context;
    }
}
