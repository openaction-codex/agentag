<?php

namespace App\Tests\Command;

use App\AgentTag\Configuration\AgentTagSettings;
use App\AgentTag\Tool\ToolCatalog;
use App\AgentTag\Workflow\WorkflowCatalog;
use App\Command\ListRepositoriesCommand;
use App\Command\ListToolsCommand;
use App\Command\ListWorkflowsCommand;
use App\Command\ValidateConfigCommand;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class AgentTagCommandTest extends TestCase
{
    private string $workflowDirectory;

    #[\Override]
    protected function setUp(): void
    {
        $this->workflowDirectory = sys_get_temp_dir().'/agentag-command-workflows-'.bin2hex(random_bytes(6));
        mkdir($this->workflowDirectory);
        file_put_contents($this->workflowDirectory.'/developer.yaml', <<<'YAML'
name: developer
description: Work on implementation tasks.
triggers:
    - implement
tools:
    - git
YAML);
        mkdir($this->workflowDirectory.'/tools');
        file_put_contents($this->workflowDirectory.'/tools/git.yaml', <<<'YAML'
name: git
type: cli
command: git
allowed_workflows:
    - developer
working_directory: codebase
environment:
    - GIT_SSH_COMMAND
timeout_seconds: 120
sensitivity: non_sensitive
sandbox: no_sandbox
YAML);
    }

    #[\Override]
    protected function tearDown(): void
    {
        foreach (glob($this->workflowDirectory.'/tools/*') ?: [] as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }

        if (is_dir($this->workflowDirectory.'/tools')) {
            rmdir($this->workflowDirectory.'/tools');
        }

        foreach (glob($this->workflowDirectory.'/*') ?: [] as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }

        if (is_dir($this->workflowDirectory)) {
            rmdir($this->workflowDirectory);
        }
    }

    public function testValidateConfigCommandSucceeds(): void
    {
        $tester = new CommandTester(new ValidateConfigCommand($this->settings(), $this->catalog()));

        $exitCode = $tester->execute([]);

        self::assertSame(Command::SUCCESS, $exitCode);
        self::assertStringContainsString('Configuration is valid', $tester->getDisplay());
    }

    public function testListRepositoriesCommandShowsConfiguredRepositories(): void
    {
        $tester = new CommandTester(new ListRepositoriesCommand($this->settings()));

        $exitCode = $tester->execute([]);

        self::assertSame(Command::SUCCESS, $exitCode);
        self::assertStringContainsString('openaction-codex-agentag', $tester->getDisplay());
        self::assertStringContainsString('git@github.com:openaction-codex/agentag.git', $tester->getDisplay());
    }

    public function testListWorkflowsCommandShowsConfiguredWorkflows(): void
    {
        $tester = new CommandTester(new ListWorkflowsCommand($this->catalog()));

        $exitCode = $tester->execute([]);

        self::assertSame(Command::SUCCESS, $exitCode);
        self::assertStringContainsString('developer', $tester->getDisplay());
        self::assertStringContainsString('implement', $tester->getDisplay());
    }

    public function testListToolsCommandShowsWorkflowTools(): void
    {
        $tester = new CommandTester(new ListToolsCommand($this->toolCatalog()));

        $exitCode = $tester->execute([]);

        self::assertSame(Command::SUCCESS, $exitCode);
        self::assertStringContainsString('git', $tester->getDisplay());
        self::assertStringContainsString('non_sensitive', $tester->getDisplay());
        self::assertStringContainsString('no_sandbox', $tester->getDisplay());
    }

    private function settings(): AgentTagSettings
    {
        return new AgentTagSettings(
            '@Codex',
            '/tmp/workspace',
            $this->workflowDirectory,
            'git@github.com:openaction-codex/agentag.git',
        );
    }

    private function catalog(): WorkflowCatalog
    {
        return new WorkflowCatalog($this->settings());
    }

    private function toolCatalog(): ToolCatalog
    {
        return new ToolCatalog($this->settings());
    }
}
