<?php

namespace App\Tests\AgentTag\Workspace;

use App\AgentTag\Workspace\WorkspaceLayout;
use PHPUnit\Framework\TestCase;

final class WorkspaceLayoutTest extends TestCase
{
    public function testItBuildsWorkspacePaths(): void
    {
        $layout = new WorkspaceLayout('/srv/agentag/workspace/', '/srv/agentag/workspace/workflows/');

        self::assertSame('/srv/agentag/workspace', $layout->workspacePath());
        self::assertSame('/srv/agentag/workspace/workflows', $layout->workflowsPath());
        self::assertSame('/srv/agentag/workspace/runs', $layout->runsPath());
        self::assertSame('/srv/agentag/workspace/runs/run-123', $layout->runPath('run-123'));
        self::assertSame('/srv/agentag/workspace/runs/run-123/codebase/api', $layout->codebasePath('run-123', 'api'));
        self::assertSame('/srv/agentag/workspace/artifacts/run-123', $layout->artifactsPath('run-123'));
        self::assertSame('/srv/agentag/workspace/cache/repositories', $layout->repositoryCachePath());
    }

    public function testItRejectsRelativeRootPaths(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('workspace path must be absolute');

        new WorkspaceLayout('workspace', '/srv/agentag/workspace/workflows');
    }

    public function testItRejectsUnsafeSegments(): void
    {
        $layout = new WorkspaceLayout('/srv/agentag/workspace', '/srv/agentag/workspace/workflows');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid workspace path segment');

        $layout->runPath('../other');
    }
}
