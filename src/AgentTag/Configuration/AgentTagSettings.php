<?php

namespace App\AgentTag\Configuration;

final readonly class AgentTagSettings
{
    private RepositoryList $repositories;

    public function __construct(
        private string $tag,
        private string $workspacePath,
        private string $workflowsPath,
        string $repositoryUrlsCsv,
    ) {
        $this->assertValidTag($tag);
        $this->assertAbsolutePath($workspacePath, 'workspace path');
        $this->assertAbsolutePath($workflowsPath, 'workflows path');

        $this->repositories = RepositoryList::fromCsv($repositoryUrlsCsv);
    }

    public function tag(): string
    {
        return $this->tag;
    }

    public function workspacePath(): string
    {
        return $this->normalizePath($this->workspacePath);
    }

    public function workflowsPath(): string
    {
        return $this->normalizePath($this->workflowsPath);
    }

    public function repositories(): RepositoryList
    {
        return $this->repositories;
    }

    private function assertValidTag(string $tag): void
    {
        if (!preg_match('/^@[A-Za-z][A-Za-z0-9_-]{1,63}$/', $tag)) {
            throw new \InvalidArgumentException(sprintf('AgentTag tag must look like @Codex, got "%s".', $tag));
        }
    }

    private function assertAbsolutePath(string $path, string $label): void
    {
        if (!str_starts_with($path, '/')) {
            throw new \InvalidArgumentException(sprintf('AgentTag %s must be absolute, got "%s".', $label, $path));
        }
    }

    private function normalizePath(string $path): string
    {
        return rtrim($path, '/');
    }
}
