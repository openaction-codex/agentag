<?php

namespace App\Tests\Command;

use App\AgentTag\Agent\WorkspaceAgentProfileProvider;
use App\AgentTag\Configuration\AgentTagSettings;
use App\AgentTag\Tool\ToolCatalog;
use App\AgentTag\Workspace\GitWorkspaceRevisionResolver;
use App\Command\ListRepositoriesCommand;
use App\Command\ListToolsCommand;
use App\Command\ValidateConfigCommand;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class AgentTagCommandTest extends TestCase
{
    private string $workspaceDirectory;

    #[\Override]
    protected function setUp(): void
    {
        $this->workspaceDirectory = sys_get_temp_dir().'/agentag-command-workspace-'.bin2hex(random_bytes(6));
        mkdir($this->workspaceDirectory.'/tools', 0777, true);
        file_put_contents($this->workspaceDirectory.'/AGENTS.md', 'Use the shared workspace instructions.');
        file_put_contents($this->workspaceDirectory.'/tools/git.yaml', <<<'YAML'
name: git
type: cli
command: git
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
        foreach (glob($this->workspaceDirectory.'/tools/*') ?: [] as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }

        if (is_dir($this->workspaceDirectory.'/tools')) {
            rmdir($this->workspaceDirectory.'/tools');
        }

        foreach (glob($this->workspaceDirectory.'/*') ?: [] as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }

        if (is_dir($this->workspaceDirectory)) {
            rmdir($this->workspaceDirectory);
        }
    }

    public function testValidateConfigCommandSucceeds(): void
    {
        $tester = new CommandTester(new ValidateConfigCommand(
            $this->settings(),
            new WorkspaceAgentProfileProvider($this->settings(), new GitWorkspaceRevisionResolver()),
            $this->toolCatalog(),
        ));

        $exitCode = $tester->execute([]);

        self::assertSame(Command::SUCCESS, $exitCode);
        self::assertStringContainsString('Configuration is valid', $tester->getDisplay());
        self::assertStringContainsString('Generic agent `agent`', $tester->getDisplay());
    }

    public function testListToolsCommandShowsWorkspaceTools(): void
    {
        $tester = new CommandTester(new ListToolsCommand($this->toolCatalog()));

        $exitCode = $tester->execute([]);

        self::assertSame(Command::SUCCESS, $exitCode);
        self::assertStringContainsString('git', $tester->getDisplay());
        self::assertStringContainsString('non_sensitive', $tester->getDisplay());
        self::assertStringContainsString('no_sandbox', $tester->getDisplay());
    }

    public function testListRepositoriesCommandShowsConfiguredRepositories(): void
    {
        $tester = new CommandTester(new ListRepositoriesCommand($this->settings()));

        $exitCode = $tester->execute([]);

        self::assertSame(Command::SUCCESS, $exitCode);
        self::assertStringContainsString('openaction-codex-agentag', $tester->getDisplay());
        self::assertStringContainsString('git@github.com:openaction-codex/agentag.git', $tester->getDisplay());
    }

    private function settings(): AgentTagSettings
    {
        return new AgentTagSettings(
            '@Codex',
            $this->workspaceDirectory,
            'git@github.com:openaction-codex/agentag.git',
        );
    }

    private function toolCatalog(): ToolCatalog
    {
        return new ToolCatalog($this->settings());
    }
}
