<?php

namespace App\Tests\Command;

use App\AgentTag\Agent\WorkspaceAgentProfileProvider;
use App\AgentTag\Configuration\AgentTagSettings;
use App\AgentTag\Workspace\GitWorkspaceRevisionResolver;
use App\Command\ListRepositoriesCommand;
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
        mkdir($this->workspaceDirectory, 0777, true);
        file_put_contents($this->workspaceDirectory.'/AGENTS.md', 'Use the shared workspace instructions.');
    }

    #[\Override]
    protected function tearDown(): void
    {
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
        ));

        $exitCode = $tester->execute([]);

        self::assertSame(Command::SUCCESS, $exitCode);
        self::assertStringContainsString('Configuration is valid', $tester->getDisplay());
        self::assertStringContainsString('Generic agent `agent`', $tester->getDisplay());
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
}
