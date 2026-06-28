<?php

namespace App\Tests\AgentTag\Codebase;

use App\AgentTag\Codebase\CodebaseContextPreparer;
use App\AgentTag\Codebase\GitRepositoryCloner;
use App\AgentTag\Codebase\RepositoryResolver;
use App\AgentTag\Configuration\AgentTagSettings;
use App\AgentTag\Runner\ProcessFactory;
use App\AgentTag\Runner\RunnerProcess;
use App\AgentTag\Workflow\WorkflowDefinition;
use App\AgentTag\Workspace\WorkspaceLayout;
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

    public function testItClonesWorkflowRepositoriesIntoPerRunCodebaseDirectory(): void
    {
        $factory = new TraceableGitProcessFactory();
        $settings = new AgentTagSettings(
            '@Codex',
            $this->workspaceDirectory,
            $this->workspaceDirectory.'/workflows',
            'git@github.com:openaction-codex/agentag.git',
        );
        $preparer = new CodebaseContextPreparer(
            new RepositoryResolver($settings),
            new GitRepositoryCloner($factory, new WorkspaceLayout($settings->workspacePath(), $settings->workflowsPath())),
        );
        $workflow = WorkflowDefinition::fromArray([
            'name' => 'developer',
            'repositories' => ['openaction-codex-agentag'],
        ], '/tmp/developer.yaml');

        $context = $preparer->prepare($workflow, 'run-123');

        self::assertSame([
            'git',
            'clone',
            '--',
            'git@github.com:openaction-codex/agentag.git',
            $this->workspaceDirectory.'/runs/run-123/codebase/openaction-codex-agentag',
        ], $factory->command);
        self::assertSame($this->workspaceDirectory.'/runs/run-123', $factory->workingDirectory);
        self::assertStringContainsString('openaction-codex-agentag', $context->promptSection());
        self::assertStringContainsString('Cite relevant file paths', $context->promptSection());
    }

    public function testItUsesLocalMirrorsAsCloneReferencesOnly(): void
    {
        $factory = new TraceableGitProcessFactory();
        $settings = new AgentTagSettings(
            '@Codex',
            $this->workspaceDirectory,
            $this->workspaceDirectory.'/workflows',
            'git@github.com:openaction-codex/agentag.git',
        );
        mkdir($this->workspaceDirectory.'/cache/repositories/openaction-codex-agentag.git', 0777, true);
        $preparer = new CodebaseContextPreparer(
            new RepositoryResolver($settings),
            new GitRepositoryCloner($factory, new WorkspaceLayout($settings->workspacePath(), $settings->workflowsPath())),
        );
        $workflow = WorkflowDefinition::fromArray([
            'name' => 'developer',
            'repositories' => ['openaction-codex-agentag'],
        ], '/tmp/developer.yaml');

        $preparer->prepare($workflow, 'run-123');

        self::assertSame([
            'git',
            'clone',
            '--reference-if-able',
            $this->workspaceDirectory.'/cache/repositories/openaction-codex-agentag.git',
            '--',
            'git@github.com:openaction-codex/agentag.git',
            $this->workspaceDirectory.'/runs/run-123/codebase/openaction-codex-agentag',
        ], $factory->command);
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

    public string $workingDirectory = '';

    #[\Override]
    public function create(array $command, string $workingDirectory, array $environment, string $input, int $timeoutSeconds): RunnerProcess
    {
        $this->command = $command;
        $this->workingDirectory = $workingDirectory;

        return new SuccessfulGitProcess();
    }
}

final readonly class SuccessfulGitProcess implements RunnerProcess
{
    #[\Override]
    public function run(): int
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
