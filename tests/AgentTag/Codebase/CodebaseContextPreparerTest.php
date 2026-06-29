<?php

namespace App\Tests\AgentTag\Codebase;

use App\AgentTag\Codebase\CodebaseContextPreparer;
use App\AgentTag\Codebase\GitRepositoryCloner;
use App\AgentTag\Codebase\RepositoryResolver;
use App\AgentTag\Configuration\AgentTagSettings;
use App\AgentTag\Runner\ProcessFactory;
use App\AgentTag\Runner\RunnerProcess;
use App\AgentTag\Workspace\WorkspaceLayout;
use App\Entity\AgentRun;
use App\Entity\ChatSession;
use PHPUnit\Framework\TestCase;

final class CodebaseContextPreparerTest extends TestCase
{
    private string $workspaceDirectory;

    #[\Override]
    protected function setUp(): void
    {
        $this->workspaceDirectory = sys_get_temp_dir().'/agentag-codebase-'.bin2hex(random_bytes(6));
    }

    #[\Override]
    protected function tearDown(): void
    {
        $this->removeDirectory($this->workspaceDirectory);
    }

    public function testItClonesConfiguredRepositoriesIntoSessionWorkspaceCodebaseDirectory(): void
    {
        $factory = new TraceableGitProcessFactory();
        $workspaceTemplate = $this->workspaceDirectory.'/workspace';
        $sessionWorkspace = $this->workspaceDirectory.'/runs/session-a';
        mkdir($sessionWorkspace, 0777, true);
        $settings = new AgentTagSettings(
            '@Codex',
            $workspaceTemplate,
            'git@github.com:openaction-codex/agentag.git',
        );
        $preparer = new CodebaseContextPreparer(
            new RepositoryResolver($settings),
            new GitRepositoryCloner($factory, new WorkspaceLayout($settings->workspacePath())),
        );
        $run = new AgentRun(
            new ChatSession('mattermost:team:channel:thread', 'mattermost', 'team', 'channel', 'thread', new \DateTimeImmutable()),
            'accepted',
            new \DateTimeImmutable(),
        );

        $context = $preparer->prepare($sessionWorkspace, $run);

        self::assertSame([
            'git',
            'clone',
            '--',
            'git@github.com:openaction-codex/agentag.git',
            $sessionWorkspace.'/codebase/openaction-codex-agentag',
        ], $factory->command);
        self::assertSame($sessionWorkspace, $factory->workingDirectory);
        self::assertStringContainsString('openaction-codex-agentag', $context->promptSection());
        self::assertStringContainsString('Cite relevant file paths', $context->promptSection());
        self::assertSame([
            'openaction-codex-agentag' => $sessionWorkspace.'/codebase/openaction-codex-agentag',
        ], $run->repositoryClones());
        self::assertSame(['openaction-codex-agentag' => 'HEAD'], $run->repositoryBaseRefs());
        self::assertSame([], $run->repositoryBranches());
    }

    public function testItUsesLocalMirrorsAsCloneReferencesOnly(): void
    {
        $factory = new TraceableGitProcessFactory();
        $workspaceTemplate = $this->workspaceDirectory.'/workspace';
        $sessionWorkspace = $this->workspaceDirectory.'/runs/session-a';
        mkdir($sessionWorkspace, 0777, true);
        $settings = new AgentTagSettings(
            '@Codex',
            $workspaceTemplate,
            'git@github.com:openaction-codex/agentag.git',
        );
        mkdir($this->workspaceDirectory.'/cache/repositories/openaction-codex-agentag.git', 0777, true);
        $preparer = new CodebaseContextPreparer(
            new RepositoryResolver($settings),
            new GitRepositoryCloner($factory, new WorkspaceLayout($settings->workspacePath())),
        );

        $preparer->prepare($sessionWorkspace);

        self::assertSame([
            'git',
            'clone',
            '--reference-if-able',
            $this->workspaceDirectory.'/cache/repositories/openaction-codex-agentag.git',
            '--',
            'git@github.com:openaction-codex/agentag.git',
            $sessionWorkspace.'/codebase/openaction-codex-agentag',
        ], $factory->command);
    }

    public function testConcurrentSessionsUseDistinctWorkingTreesForTheSameRepository(): void
    {
        $factory = new TraceableGitProcessFactory();
        $workspaceTemplate = $this->workspaceDirectory.'/workspace';
        $firstWorkspace = $this->workspaceDirectory.'/runs/session-a';
        $secondWorkspace = $this->workspaceDirectory.'/runs/session-b';
        mkdir($firstWorkspace, 0777, true);
        mkdir($secondWorkspace, 0777, true);
        $settings = new AgentTagSettings(
            '@Codex',
            $workspaceTemplate,
            'git@github.com:openaction-codex/agentag.git',
        );
        $preparer = new CodebaseContextPreparer(
            new RepositoryResolver($settings),
            new GitRepositoryCloner($factory, new WorkspaceLayout($settings->workspacePath())),
        );

        $first = $preparer->prepare($firstWorkspace);
        $second = $preparer->prepare($secondWorkspace);

        self::assertCount(2, $factory->commands);
        self::assertSame($firstWorkspace, $factory->workingDirectories[0]);
        self::assertSame($secondWorkspace, $factory->workingDirectories[1]);
        self::assertSame($firstWorkspace.'/codebase/openaction-codex-agentag', $factory->commands[0][4]);
        self::assertSame($secondWorkspace.'/codebase/openaction-codex-agentag', $factory->commands[1][4]);
        self::assertNotSame($factory->commands[0][4], $factory->commands[1][4]);
        self::assertSame([
            'openaction-codex-agentag' => $firstWorkspace.'/codebase/openaction-codex-agentag',
        ], $first->cloneMap());
        self::assertSame([
            'openaction-codex-agentag' => $secondWorkspace.'/codebase/openaction-codex-agentag',
        ], $second->cloneMap());
        self::assertSame(['openaction-codex-agentag' => 'HEAD'], $first->baseRefMap());
        self::assertSame([], $first->branchMap());
    }

    private function removeDirectory(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($files as $file) {
            if (!$file instanceof \SplFileInfo) {
                continue;
            }

            $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        }

        rmdir($path);
    }
}

final class TraceableGitProcessFactory implements ProcessFactory
{
    /**
     * @var list<string>
     */
    public array $command = [];

    /**
     * @var list<list<string>>
     */
    public array $commands = [];

    public string $workingDirectory = '';

    /**
     * @var list<string>
     */
    public array $workingDirectories = [];

    #[\Override]
    public function create(array $command, string $workingDirectory, array $environment, string $input, int $timeoutSeconds): RunnerProcess
    {
        $this->command = $command;
        $this->commands[] = $command;
        $this->workingDirectory = $workingDirectory;
        $this->workingDirectories[] = $workingDirectory;

        return new SuccessfulGitProcess();
    }
}

final readonly class SuccessfulGitProcess implements RunnerProcess
{
    #[\Override]
    public function run(?callable $callback = null): int
    {
        return 0;
    }

    #[\Override]
    public function exitCode(): int
    {
        return 0;
    }

    #[\Override]
    public function output(): string
    {
        return '';
    }

    #[\Override]
    public function errorOutput(): string
    {
        return '';
    }
}
