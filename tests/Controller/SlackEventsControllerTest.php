<?php

namespace App\Tests\Controller;

use App\Entity\AgentRun;
use App\Entity\ChatSession;
use App\Tests\RefreshDatabaseTrait;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class SlackEventsControllerTest extends WebTestCase
{
    use RefreshDatabaseTrait;

    public function testItHandlesUrlVerification(): void
    {
        $client = $this->createClientWithFreshDatabase();

        $client->jsonRequest('POST', '/integrations/slack/events', [
            'type' => 'url_verification',
            'challenge' => 'challenge-token',
        ]);

        self::assertResponseIsSuccessful();
        self::assertStringContainsString('challenge-token', (string) $client->getResponse()->getContent());
    }

    public function testItIgnoresMessagesWithoutTheConfiguredTag(): void
    {
        $client = $this->createClientWithFreshDatabase();

        $client->jsonRequest('POST', '/integrations/slack/events', $this->payload(['text' => 'hello']));

        self::assertResponseIsSuccessful();
        self::assertStringContainsString('"handled":false', (string) $client->getResponse()->getContent());
        self::assertSame(0, $this->entityCount(ChatSession::class));
        self::assertSame(0, $this->entityCount(AgentRun::class));
    }

    public function testItAcceptsMentionedRootMessages(): void
    {
        $client = $this->createClientWithFreshDatabase();

        $client->jsonRequest('POST', '/integrations/slack/events', $this->payload(['text' => '@Codex help']));

        self::assertResponseIsSuccessful();
        self::assertStringContainsString('Accepted workflow `developer`', (string) $client->getResponse()->getContent());
        self::assertStringContainsString('session `1700000000.000000`', (string) $client->getResponse()->getContent());
        self::assertSame(1, $this->entityCount(ChatSession::class));
        self::assertSame(1, $this->entityCount(AgentRun::class));
    }

    public function testItContinuesThreadRepliesUsingThreadTs(): void
    {
        $client = $this->createClientWithFreshDatabase();

        $client->jsonRequest('POST', '/integrations/slack/events', $this->payload([
            'text' => '@Codex continue',
            'thread_ts' => '1699999999.000000',
        ]));

        self::assertResponseIsSuccessful();
        self::assertStringContainsString('session `1699999999.000000`', (string) $client->getResponse()->getContent());
        self::assertSame(1, $this->entityCount(ChatSession::class));
        self::assertSame(1, $this->entityCount(AgentRun::class));
    }

    public function testItRejectsInvalidPayloads(): void
    {
        $client = $this->createClientWithFreshDatabase();

        $client->jsonRequest('POST', '/integrations/slack/events', ['type' => 'event_callback']);

        self::assertResponseStatusCodeSame(400);
        self::assertSame(0, $this->entityCount(ChatSession::class));
        self::assertSame(0, $this->entityCount(AgentRun::class));
    }

    /**
     * @param array<string, string> $eventOverrides
     *
     * @return array<string, mixed>
     */
    private function payload(array $eventOverrides = []): array
    {
        return [
            'type' => 'event_callback',
            'event_id' => 'Ev123',
            'team_id' => 'T123',
            'token' => '',
            'event' => array_replace([
                'type' => 'app_mention',
                'text' => '@Codex help',
                'ts' => '1700000000.000000',
                'thread_ts' => '',
                'channel' => 'C123',
                'user' => 'U123',
            ], $eventOverrides),
        ];
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
