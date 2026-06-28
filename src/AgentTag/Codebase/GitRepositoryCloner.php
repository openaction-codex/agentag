<?php

namespace App\AgentTag\Codebase;

use App\AgentTag\Configuration\ConfiguredRepository;
use App\AgentTag\Runner\ProcessFactory;
use App\AgentTag\Workspace\WorkspaceLayout;
use Psr\Log\LoggerInterface;

final readonly class GitRepositoryCloner
{
    public function __construct(
        private ProcessFactory $processFactory,
        private WorkspaceLayout $workspaceLayout,
        private ?LoggerInterface $logger = null,
    ) {
    }

    public function cloneForRun(ConfiguredRepository $repository, string $runIdentifier): RepositoryClone
    {
        $runPath = $this->workspaceLayout->runPath($runIdentifier);
        $targetPath = $this->workspaceLayout->codebasePath($runIdentifier, $repository->identifier());

        if (!is_dir($runPath.'/codebase')) {
            mkdir($runPath.'/codebase', 0777, true);
        }

        $this->logger?->info('Cloning repository for run.', [
            'repository' => $repository->identifier(),
            'run_identifier' => $runIdentifier,
            'target_path' => $targetPath,
        ]);

        $process = $this->processFactory->create(
            $this->cloneCommand($repository, $targetPath),
            $runPath,
            [],
            '',
            600,
        );
        $process->run();

        if (0 !== $process->exitCode()) {
            $this->logger?->error('Repository clone failed.', [
                'repository' => $repository->identifier(),
                'run_identifier' => $runIdentifier,
                'exit_code' => $process->exitCode(),
            ]);
            throw new \RuntimeException(sprintf('Repository `%s` could not be cloned: %s', $repository->identifier(), trim($process->errorOutput()) ?: trim($process->output())));
        }

        $this->logger?->info('Repository clone completed.', [
            'repository' => $repository->identifier(),
            'run_identifier' => $runIdentifier,
            'target_path' => $targetPath,
        ]);

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
