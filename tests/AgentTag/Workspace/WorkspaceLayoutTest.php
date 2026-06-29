<?php

namespace App\Tests\AgentTag\Workspace;

use App\AgentTag\Workspace\WorkspaceLayout;
use PHPUnit\Framework\TestCase;

final class WorkspaceLayoutTest extends TestCase
{
    public function testItBuildsWorkspacePaths(): void
    {
        $layout = new WorkspaceLayout('/srv/agentag/workspace/');

        self::assertSame('/srv/agentag/workspace', $layout->workspacePath());
        self::assertSame('/srv/agentag', $layout->runtimeRootPath());
        self::assertSame('/srv/agentag/runs', $layout->runsPath());
        self::assertSame('/srv/agentag/runs/run-123', $layout->runPath('run-123'));
        self::assertSame('/srv/agentag/runs/run-123/codebase/api', $layout->codebasePath('run-123', 'api'));
        self::assertSame('/srv/agentag/artifacts/run-123', $layout->artifactsPath('run-123'));
        self::assertSame('/srv/agentag/cache/repositories', $layout->repositoryCachePath());
        self::assertSame('/srv/agentag/runs/session-'.substr(sha1('mattermost:team:channel:thread'), 0, 16), $layout->sessionPath('mattermost:team:channel:thread'));
    }

    public function testItRejectsRelativeRootPaths(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('workspace path must be absolute');

        new WorkspaceLayout('workspace');
    }

    public function testItRejectsUnsafeSegments(): void
    {
        $layout = new WorkspaceLayout('/srv/agentag/workspace');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid workspace path segment');

        $layout->runPath('../other');
    }
}
