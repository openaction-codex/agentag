<?php

namespace App\AgentTag\Configuration;

final readonly class AgentTagSettings
{
    public function __construct(
        private string $tag,
        private string $workspacePath,
        private int $runTimeoutSeconds = 1200,
    ) {
        $this->assertValidTag($tag);
        $this->assertAbsolutePath($workspacePath, 'workspace path');
        if ($runTimeoutSeconds < 1) {
            throw new \InvalidArgumentException('AgentTag run timeout must be a positive integer.');
        }
    }

    public function tag(): string
    {
        return $this->tag;
    }

    public function workspacePath(): string
    {
        return $this->normalizePath($this->workspacePath);
    }

    public function runTimeoutSeconds(): int
    {
        return $this->runTimeoutSeconds;
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
