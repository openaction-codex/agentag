<?php

namespace App\AgentTag\Workspace;

final readonly class WorkspaceLayout
{
    public function __construct(
        private string $workspacePath,
        private string $workflowsPath,
    ) {
        $this->assertAbsolutePath($workspacePath, 'workspace path');
        $this->assertAbsolutePath($workflowsPath, 'workflows path');
    }

    public function workspacePath(): string
    {
        return $this->normalize($this->workspacePath);
    }

    public function workflowsPath(): string
    {
        return $this->normalize($this->workflowsPath);
    }

    public function runsPath(): string
    {
        return $this->workspacePath().'/runs';
    }

    public function runPath(string $runId): string
    {
        return $this->runsPath().'/'.$this->safeSegment($runId);
    }

    public function codebasePath(string $runId, string $repositoryName): string
    {
        return $this->runPath($runId).'/codebase/'.$this->safeSegment($repositoryName);
    }

    public function artifactsPath(string $runId): string
    {
        return $this->workspacePath().'/artifacts/'.$this->safeSegment($runId);
    }

    public function repositoryCachePath(): string
    {
        return $this->workspacePath().'/cache/repositories';
    }

    private function assertAbsolutePath(string $path, string $label): void
    {
        if (!str_starts_with($path, '/')) {
            throw new \InvalidArgumentException(sprintf('AgentTag %s must be absolute, got "%s".', $label, $path));
        }
    }

    private function normalize(string $path): string
    {
        return rtrim($path, '/');
    }

    private function safeSegment(string $segment): string
    {
        if ('' === $segment || str_contains($segment, '/') || str_contains($segment, '..')) {
            throw new \InvalidArgumentException(sprintf('Invalid workspace path segment "%s".', $segment));
        }

        return $segment;
    }
}
