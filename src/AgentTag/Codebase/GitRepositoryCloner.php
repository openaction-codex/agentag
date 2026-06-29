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

    public function cloneForWorkspace(ConfiguredRepository $repository, string $workspacePath): RepositoryClone
    {
        $targetPath = $this->workspaceLayout->codebasePathForWorkspace($workspacePath, $repository->identifier());

        if (!is_dir($workspacePath.'/codebase')) {
            mkdir($workspacePath.'/codebase', 0777, true);
        }

        if (is_dir($targetPath.'/.git')) {
            return new RepositoryClone($repository, $targetPath);
        }

        $this->logger?->info('Cloning repository for session workspace.', [
            'repository' => $repository->identifier(),
            'workspace_path' => $workspacePath,
            'target_path' => $targetPath,
        ]);

        $process = $this->processFactory->create(
            $this->cloneCommand($repository, $targetPath),
            $workspacePath,
            [],
            '',
            600,
        );
        $process->run();

        if (0 !== $process->exitCode()) {
            $this->logger?->error('Repository clone failed.', [
                'repository' => $repository->identifier(),
                'workspace_path' => $workspacePath,
                'exit_code' => $process->exitCode(),
            ]);
            throw new \RuntimeException(sprintf('Repository `%s` could not be cloned: %s', $repository->identifier(), trim($process->errorOutput()) ?: trim($process->output())));
        }

        $this->logger?->info('Repository clone completed.', [
            'repository' => $repository->identifier(),
            'workspace_path' => $workspacePath,
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
