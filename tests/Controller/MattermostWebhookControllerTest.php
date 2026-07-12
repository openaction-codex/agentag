<?php

namespace App\Tests\Controller;

use App\Entity\AgentRun;
use App\Entity\ChatSession;
use App\Entity\RunEvent;
use App\Tests\RefreshDatabaseTrait;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class MattermostWebhookControllerTest extends WebTestCase
{
    use RefreshDatabaseTrait;

    public function testItIgnoresMessagesWithoutTheConfiguredTag(): void
    {
        $client = $this->createClientWithFreshDatabase();

        $client->jsonRequest('POST', '/integrations/mattermost/webhook', $this->payload(['text' => 'hello']));

        self::assertResponseStatusCodeSame(204);
        self::assertSame(0, $this->entityCount(ChatSession::class));
        self::assertSame(0, $this->entityCount(AgentRun::class));
    }

    public function testItAcceptsMentionedRootMessages(): void
    {
        $client = $this->createClientWithFreshDatabase();

        $client->jsonRequest('POST', '/integrations/mattermost/webhook', $this->payload(['text' => '@Codex help']));

        self::assertResponseStatusCodeSame(204);
        self::assertSame(1, $this->entityCount(ChatSession::class));
        self::assertSame(1, $this->entityCount(AgentRun::class));
        self::assertSame(0, $this->entityCount(RunEvent::class));

        $run = $this->firstRun();
        self::assertSame('post-id', $run->sourceEventId());
        self::assertSame('user-id', $run->requesterId());
    }

    public function testItContinuesThreadRepliesUsingTheRootId(): void
    {
        $client = $this->createClientWithFreshDatabase();

        $client->jsonRequest('POST', '/integrations/mattermost/webhook', $this->payload([
            'text' => '@Codex continue',
            'post_id' => 'reply-id',
            'root_id' => 'root-id',
        ]));

        self::assertResponseStatusCodeSame(204);
        self::assertSame(1, $this->entityCount(ChatSession::class));
        self::assertSame(1, $this->entityCount(AgentRun::class));
    }

    public function testItRejectsInvalidPayloads(): void
    {
        $client = $this->createClientWithFreshDatabase();

        $client->jsonRequest('POST', '/integrations/mattermost/webhook', ['text' => '@Codex help']);

        self::assertResponseStatusCodeSame(400);
        self::assertSame(0, $this->entityCount(ChatSession::class));
        self::assertSame(0, $this->entityCount(AgentRun::class));
    }

    /**
     * @param array<string, string> $overrides
     *
     * @return array<string, string>
     */
    private function payload(array $overrides = []): array
    {
        return array_replace([
            'post_id' => 'post-id',
            'text' => '@Codex help',
            'root_id' => '',
            'channel_id' => 'channel-id',
            'channel_type' => 'O',
            'team_id' => 'team-id',
            'user_id' => 'user-id',
            'token' => '',
        ], $overrides);
    }

    private function createClientWithFreshDatabase(): KernelBrowser
    {
        $client = static::createClient();
        $this->refreshDatabase();
        $this->writeTestWorkspace();

        return $client;
    }

    private function writeTestWorkspace(): void
    {
        $projectDirectory = static::getContainer()->getParameter('kernel.project_dir');
        if (!is_string($projectDirectory)) {
            throw new \LogicException('Kernel project directory must be available.');
        }

        $workspaceDirectory = $projectDirectory.'/var/test-workspace';
        $this->removeDirectory($workspaceDirectory);
        if (!is_dir($workspaceDirectory)) {
            mkdir($workspaceDirectory, 0777, true);
        }

        file_put_contents($workspaceDirectory.'/AGENTS.md', 'Use the shared workspace instructions.');
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

    /**
     * @param class-string<object> $className
     */
    private function entityCount(string $className): int
    {
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $entityManager);

        return count($entityManager->getRepository($className)->findAll());
    }

    private function firstRun(): AgentRun
    {
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $entityManager);
        $run = $entityManager->getRepository(AgentRun::class)->findOneBy([]);
        self::assertInstanceOf(AgentRun::class, $run);

        return $run;
    }
}
