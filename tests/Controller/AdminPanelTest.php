<?php

namespace App\Tests\Controller;

use App\AgentTag\Approval\ActionSensitivity;
use App\Entity\AgentRun;
use App\Entity\ApprovalRequest;
use App\Entity\ChatSession;
use App\Entity\GlobalMemory;
use App\Entity\LinearWriteAudit;
use App\Entity\RunEvent;
use App\Tests\RefreshDatabaseTrait;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class AdminPanelTest extends WebTestCase
{
    use RefreshDatabaseTrait;

    public function testAdminPanelExposesReadOnlyEntityIndexes(): void
    {
        $client = $this->authenticatedClient();
        $this->seedUsageData();

        $crawler = $client->request('GET', '/admin/global-memory');

        self::assertResponseIsSuccessful();
        self::assertStringContainsString('Global memories', (string) $client->getResponse()->getContent());
        self::assertStringContainsString('Remember password=[REDACTED]', (string) $client->getResponse()->getContent());
        self::assertStringNotContainsString('hunter2', (string) $client->getResponse()->getContent());
        self::assertStringNotContainsString('/admin/global-memory/new', (string) $client->getResponse()->getContent());
        self::assertStringNotContainsString('/edit', (string) $client->getResponse()->getContent());
        self::assertSame(0, $crawler->filter('form[action*="/delete"], a[href*="/delete"]')->count());
    }

    public function testAdminPanelRejectsDirectWriteRoutes(): void
    {
        $client = $this->authenticatedClient();
        $memoryId = $this->seedUsageData();

        $client->request('GET', '/admin/global-memory/new');
        self::assertResponseStatusCodeSame(403);

        $client->request('GET', sprintf('/admin/global-memory/%d/edit', $memoryId));
        self::assertResponseStatusCodeSame(403);
    }

    private function authenticatedClient(): KernelBrowser
    {
        $client = static::createClient();
        $this->refreshDatabase();
        $client->setServerParameter('PHP_AUTH_USER', 'admin');
        $client->setServerParameter('PHP_AUTH_PW', 'change-me');

        return $client;
    }

    private function seedUsageData(): int
    {
        $now = new \DateTimeImmutable('2026-06-28T22:30:00+00:00');
        $session = new ChatSession('mattermost:team:channel:thread', 'mattermost', 'team', 'channel', 'thread', $now, 'Thread summary');
        $run = new AgentRun(
            $session,
            'completed',
            $now,
            'Implement the spec password=hunter2',
            'Done password=hunter2',
            '{"password":"hunter2"}',
            'agent',
            null,
            'abc123',
            'event-1',
            'user-1',
        );
        $run->recordRunnerResult('completed', 'Done password=hunter2', 'log password=hunter2', '/tmp/agentag/run-1', ['artifact-password=hunter2'], 0, null);
        $run->recordRepositoryClones(['repo' => '/tmp/agentag/run-1/repo'], ['repo' => 'main'], ['repo' => 'agentag/run-1']);

        $memory = new GlobalMemory('Remember password=hunter2', $now, 'user-1', 'mattermost', 'thread', 'message');
        $event = new RunEvent($run, RunEvent::TYPE_PROGRESS_UPDATE, 'Progress password=hunter2', ['token' => 'secret123456789'], $now);
        $approval = new ApprovalRequest($run, 'force_push', 'git', 'agent', 'user-1', 'Force push password=hunter2', ActionSensitivity::DESTRUCTIVE, $now);
        $linearAudit = LinearWriteAudit::failed('comment', 'message', 'agent', 'user-1', 'OPE-1', 'Linear failed password=hunter2', $now);

        $entityManager = $this->entityManager();
        $entityManager->persist($session);
        $entityManager->persist($run);
        $entityManager->persist($memory);
        $entityManager->persist($event);
        $entityManager->persist($approval);
        $entityManager->persist($linearAudit);
        $entityManager->flush();

        $memoryId = $memory->id();
        self::assertNotNull($memoryId);

        return $memoryId;
    }

    private function entityManager(): EntityManagerInterface
    {
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $entityManager);

        return $entityManager;
    }
}
