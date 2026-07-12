<?php

namespace App\AgentTag\Configuration;

final readonly class AgentTagSettings
{
    public function __construct(
        private string $tag,
        private string $workspacePath,
        private int $runTimeoutSeconds = 1200,
        private string $acknowledgementModel = 'gpt-5.6-luna',
        private int $acknowledgementTimeoutSeconds = 20,
        private int $taskDeadlineSeconds = 86400,
        private int $maxRetries = 2,
        private int $retryDelaySeconds = 60,
        private string $notificationPreference = 'milestones',
    ) {
        $this->assertValidTag($tag);
        $this->assertAbsolutePath($workspacePath, 'workspace path');
        if ($runTimeoutSeconds < 1) {
            throw new \InvalidArgumentException('AgentTag run timeout must be a positive integer.');
        }
        if ($acknowledgementTimeoutSeconds < 1 || $taskDeadlineSeconds < 1 || $maxRetries < 0 || $retryDelaySeconds < 1) {
            throw new \InvalidArgumentException('AgentTag task timing and retry settings are invalid.');
        }
        if (!in_array($notificationPreference, ['all', 'milestones', 'completion'], true)) {
            throw new \InvalidArgumentException('AgentTag notification preference must be all, milestones, or completion.');
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

    public function acknowledgementModel(): string
    {
        return $this->acknowledgementModel;
    }

    public function acknowledgementTimeoutSeconds(): int
    {
        return $this->acknowledgementTimeoutSeconds;
    }

    public function taskDeadlineSeconds(): int
    {
        return $this->taskDeadlineSeconds;
    }

    public function maxRetries(): int
    {
        return $this->maxRetries;
    }

    public function retryDelaySeconds(): int
    {
        return $this->retryDelaySeconds;
    }

    public function notificationPreference(): string
    {
        return $this->notificationPreference;
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
