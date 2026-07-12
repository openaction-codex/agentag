<?php

namespace App\Tests\Command;

use App\AgentTag\Workspace\WorkspaceLayout;
use App\Command\CleanupWorkspaceCommand;
use App\Entity\AgentRun;
use App\Entity\ChatSession;
use App\Tests\RefreshDatabaseTrait;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Filesystem\Filesystem;

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
        mkdir($this->workspaceDirectory.'/workspace', 0777, true);
        mkdir($this->workspaceDirectory.'/runs', 0777, true);
        mkdir($this->workspaceDirectory.'/artifacts', 0777, true);
    }

    #[\Override]
    protected function tearDown(): void
    {
        $this->removeDirectory($this->workspaceDirectory);
    }

    public function testWorkspaceCleanupIsDryRunUnlessForced(): void
    {
        $runDirectory = $this->workspaceDirectory.'/runs/old-run';
        mkdir($runDirectory);
        touch($runDirectory, time() - 172800);
        $session = new ChatSession('mattermost:team:channel:thread', 'team', 'channel', 'thread', new \DateTimeImmutable());
        $run = new AgentRun($session, 'completed', new \DateTimeImmutable());
        $run->recordRunnerResult('completed', 'done', 'log', $runDirectory, [], 0, null);
        $this->entityManager()->persist($session);
        $this->entityManager()->persist($run);
        $this->entityManager()->flush();

        $command = new CleanupWorkspaceCommand($this->workspaceLayout(), $this->entityManager(), new Filesystem());
        $dryRun = new CommandTester($command);

        self::assertSame(Command::SUCCESS, $dryRun->execute(['--older-than-days' => '1']));
        self::assertDirectoryExists($runDirectory);
        self::assertSame(AgentRun::WORKSPACE_CLEANUP_RETAINED, $run->workspaceCleanupState());
        self::assertStringContainsString('Dry run only', $dryRun->getDisplay());

        $forced = new CommandTester($command);
        self::assertSame(Command::SUCCESS, $forced->execute(['--older-than-days' => '1', '--force' => true]));
        self::assertDirectoryDoesNotExist($runDirectory);
        self::assertSame(AgentRun::WORKSPACE_CLEANUP_CLEANED, $run->workspaceCleanupState());
    }

    private function entityManager(): EntityManagerInterface
    {
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $entityManager);

        return $entityManager;
    }

    private function workspaceLayout(): WorkspaceLayout
    {
        return new WorkspaceLayout($this->workspaceDirectory.'/workspace');
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
