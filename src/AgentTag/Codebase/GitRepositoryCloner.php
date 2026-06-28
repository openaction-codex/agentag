<?php

namespace App\AgentTag\Codebase;

use App\AgentTag\Configuration\ConfiguredRepository;
use App\AgentTag\Runner\ProcessFactory;
use App\AgentTag\Workspace\WorkspaceLayout;

final readonly class GitRepositoryCloner
{
    public function __construct(
        private ProcessFactory $processFactory,
        private WorkspaceLayout $workspaceLayout,
    ) {
    }

    public function cloneForRun(ConfiguredRepository $repository, string $runIdentifier): RepositoryClone
    {
        $runPath = $this->workspaceLayout->runPath($runIdentifier);
        $targetPath = $this->workspaceLayout->codebasePath($runIdentifier, $repository->identifier());

        if (!is_dir($runPath.'/codebase')) {
            mkdir($runPath.'/codebase', 0777, true);
        }

        $process = $this->processFactory->create(
            $this->cloneCommand($repository, $targetPath),
            $runPath,
            [],
            '',
            600,
        );
        $process->run();

        if (0 !== $process->exitCode()) {
            throw new \RuntimeException(sprintf('Repository `%s` could not be cloned: %s', $repository->identifier(), trim($process->errorOutput()) ?: trim($process->output())));
        }

        return new RepositoryClone($repository, $targetPath);
    }

    /**
     * @return list<string>
     */
    private function cloneCommand(ConfiguredRepository $repository, string $targetPath): array
    {
        $cachePath = $this->workspaceLayout->repositoryCachePath().'/'.$repository->identifier().'.git';
        if (is_dir($cachePath)) {
            return ['git', 'clone', '--reference-if-able', $cachePath, '--', $repository->url(), $targetPath];
        }

        return ['git', 'clone', '--', $repository->url(), $targetPath];
    }
}
