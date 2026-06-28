<?php

namespace App\Tests\Command;

use App\AgentTag\Configuration\AgentTagSettings;
use App\AgentTag\Memory\GlobalMemoryCommandContext;
use App\AgentTag\Memory\GlobalMemoryService;
use App\AgentTag\Workspace\WorkspaceLayout;
use App\Command\CleanupWorkspaceCommand;
use App\Command\DeleteMemoryCommand;
use App\Command\InspectWorkspaceCommand;
use App\Command\ListFailedRunsCommand;
use App\Command\ListMemoriesCommand;
use App\Entity\AgentRun;
use App\Entity\ChatSession;
use App\Tests\RefreshDatabaseTrait;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class OperatorCommandTest extends KernelTestCase
{
    use RefreshDatabaseTrait;

    private string $workspaceDirectory;

    #[\Override]
    protected function setUp(): void
    {
        self::bootKernel();
        $this->refreshDatabase();
        $this->workspaceDirectory = sys_get_temp_dir().'/agentag-operator-'.bin2hex(random_bytes(6));
        mkdir($this->workspaceDirectory.'/runs', 0777, true);
        mkdir($this->workspaceDirectory.'/artifacts', 0777, true);
        mkdir($this->workspaceDirectory.'/workflows', 0777, true);
    }

    #[\Override]
    protected function tearDown(): void
    {
        $this->removeDirectory($this->workspaceDirectory);
    }

    public function testFailedRunCommandShowsSanitizedRunMetadata(): void
    {
        $session = new ChatSession('mattermost:team:channel:thread', 'mattermost', 'team', 'channel', 'thread', new \DateTimeImmutable());
        $run = new AgentRun($session, 'failed', new \DateTimeImmutable(), 'input', 'output', null, 'developer', 'v1', 'abc123', 'event-1', 'user-1');
        $this->entityManager()->persist($session);
        $this->entityManager()->persist($run);
        $this->entityManager()->flush();

        $tester = new CommandTester(new ListFailedRunsCommand($this->entityManager()));

        self::assertSame(Command::SUCCESS, $tester->execute([]));
        self::assertStringContainsString('developer', $tester->getDisplay());
        self::assertStringContainsString('event-1', $tester->getDisplay());
        self::assertStringContainsString('user-1', $tester->getDisplay());
    }

    public function testMemoryCommandsListAndDeleteExplicitMemories(): void
    {
        $this->memoryService()->rememberExplicit(
            'Prefer concise Mattermost updates.',
            new GlobalMemoryCommandContext('mattermost', 'user-1', 'thread-1', 'message-1'),
        );

        $listTester = new CommandTester(new ListMemoriesCommand($this->memoryService()));
        self::assertSame(Command::SUCCESS, $listTester->execute([]));
        self::assertStringContainsString('Prefer concise Mattermost updates.', $listTester->getDisplay());

        $memories = $this->memoryService()->all();
        self::assertCount(1, $memories);
        $memoryId = $memories[0]->id();
        self::assertNotNull($memoryId);

        $deleteTester = new CommandTester(new DeleteMemoryCommand($this->memoryService()));
        self::assertSame(Command::SUCCESS, $deleteTester->execute(['id' => (string) $memoryId]));
        self::assertStringContainsString(sprintf('Deleted global memory #%d.', $memoryId), $deleteTester->getDisplay());
        self::assertSame([], $this->memoryService()->all());
    }

    public function testWorkspaceInspectShowsPathsAndRepositories(): void
    {
        $tester = new CommandTester(new InspectWorkspaceCommand($this->workspaceLayout(), $this->settings()));

        self::assertSame(Command::SUCCESS, $tester->execute([]));
        self::assertStringContainsString($this->workspaceDirectory, $tester->getDisplay());
        self::assertStringContainsString('openaction-codex-agentag', $tester->getDisplay());
    }

    public function testWorkspaceCleanupIsDryRunUnlessForced(): void
    {
        $runDirectory = $this->workspaceDirectory.'/runs/old-run';
        mkdir($runDirectory);
        touch($runDirectory, time() - 172800);

        $command = new CleanupWorkspaceCommand($this->workspaceLayout());
        $dryRun = new CommandTester($command);

        self::assertSame(Command::SUCCESS, $dryRun->execute(['--older-than-days' => '1']));
        self::assertDirectoryExists($runDirectory);
        self::assertStringContainsString('Dry run only', $dryRun->getDisplay());

        $forced = new CommandTester($command);
        self::assertSame(Command::SUCCESS, $forced->execute(['--older-than-days' => '1', '--force' => true]));
        self::assertDirectoryDoesNotExist($runDirectory);
    }

    private function memoryService(): GlobalMemoryService
    {
        $service = static::getContainer()->get(GlobalMemoryService::class);
        self::assertInstanceOf(GlobalMemoryService::class, $service);

        return $service;
    }

    private function entityManager(): EntityManagerInterface
    {
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $entityManager);

        return $entityManager;
    }

    private function settings(): AgentTagSettings
    {
        return new AgentTagSettings(
            '@Codex',
            $this->workspaceDirectory,
            $this->workspaceDirectory.'/workflows',
            'git@github.com:openaction-codex/agentag.git',
        );
    }

    private function workspaceLayout(): WorkspaceLayout
    {
        return new WorkspaceLayout($this->workspaceDirectory, $this->workspaceDirectory.'/workflows');
    }

    private function removeDirectory(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        foreach (array_reverse(glob($path.'/{,.}*', \GLOB_BRACE) ?: []) as $file) {
            if ('.' === basename($file) || '..' === basename($file)) {
                continue;
            }

            is_dir($file) ? $this->removeDirectory($file) : unlink($file);
        }

        rmdir($path);
    }
}
