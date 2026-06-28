<?php

namespace App\Tests\Controller;

use App\Entity\AgentRun;
use App\Entity\ChatSession;
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

        self::assertResponseIsSuccessful();
        self::assertResponseFormatSame('json');
        self::assertStringContainsString('Accepted workflow `developer`', (string) $client->getResponse()->getContent());
        self::assertStringContainsString('session `post-id`', (string) $client->getResponse()->getContent());
        self::assertSame(1, $this->entityCount(ChatSession::class));
        self::assertSame(1, $this->entityCount(AgentRun::class));
    }

    public function testItContinuesThreadRepliesUsingTheRootId(): void
    {
        $client = $this->createClientWithFreshDatabase();

        $client->jsonRequest('POST', '/integrations/mattermost/webhook', $this->payload([
            'text' => '@Codex continue',
            'post_id' => 'reply-id',
            'root_id' => 'root-id',
        ]));

        self::assertResponseIsSuccessful();
        self::assertStringContainsString('session `root-id`', (string) $client->getResponse()->getContent());
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
        $this->writeTestWorkflow();

        return $client;
    }

    private function writeTestWorkflow(): void
    {
        $projectDirectory = static::getContainer()->getParameter('kernel.project_dir');
        if (!is_string($projectDirectory)) {
            throw new \LogicException('Kernel project directory must be available.');
        }

        $workflowDirectory = $projectDirectory.'/var/test-workflows';
        if (!is_dir($workflowDirectory)) {
            mkdir($workflowDirectory, 0777, true);
        }

        file_put_contents($workflowDirectory.'/developer.yaml', <<<'YAML'
name: developer
version: v1
default: true
triggers:
    - help
    - continue
tools:
    - codex
YAML);
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
}
