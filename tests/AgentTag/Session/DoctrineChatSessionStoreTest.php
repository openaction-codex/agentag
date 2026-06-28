<?php

namespace App\Tests\AgentTag\Session;

use App\AgentTag\Chat\ChatSessionReference;
use App\AgentTag\Session\ChatSessionStore;
use App\AgentTag\Session\ChatThreadContext;
use App\AgentTag\Session\ChatThreadMessage;
use App\AgentTag\Workflow\WorkflowDefinition;
use App\Entity\AgentRun;
use App\Entity\ChatSession;
use App\Tests\RefreshDatabaseTrait;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class DoctrineChatSessionStoreTest extends KernelTestCase
{
    use RefreshDatabaseTrait;

    #[\Override]
    protected function setUp(): void
    {
        self::bootKernel();
        $this->refreshDatabase();
    }

    public function testItCreatesOneSessionAndAppendsRunsForTheSameThread(): void
    {
        $store = static::getContainer()->get(ChatSessionStore::class);
        self::assertInstanceOf(ChatSessionStore::class, $store);

        $reference = new ChatSessionReference('mattermost', 'team-id', 'channel-id', 'root-id');
        $workflow = WorkflowDefinition::fromArray(
            ['name' => 'developer', 'version' => 'v1'],
            '/tmp/developer.yaml',
            'abc123',
        );

        $store->recordRun($reference, 'first input token=secret-token', new ChatThreadContext([
            new ChatThreadMessage('root-id', 'user-a', '@Codex help password=hunter2'),
        ]), $workflow);
        $store->recordRun($reference, 'second input', new ChatThreadContext([
            new ChatThreadMessage('root-id', 'user-a', '@Codex help'),
            new ChatThreadMessage('reply-id', 'user-b', 'continue with Bearer abcdefghijklmnop'),
        ]), $workflow);

        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $entityManager);

        $sessions = $entityManager->getRepository(ChatSession::class)->findAll();
        $runs = $entityManager->getRepository(AgentRun::class)->findBy([], ['id' => 'ASC']);

        self::assertCount(1, $sessions);
        self::assertCount(2, $runs);

        $session = $sessions[0];
        self::assertSame('mattermost:team-id:channel-id:root-id', $session->sessionKey());
        self::assertSame('mattermost', $session->platform());
        self::assertSame('team-id', $session->teamId());
        self::assertSame('channel-id', $session->channelId());
        self::assertSame('root-id', $session->threadId());

        self::assertSame($session, $runs[0]->session());
        self::assertSame('accepted', $runs[0]->status());
        self::assertSame('developer', $runs[0]->workflowName());
        self::assertSame('v1', $runs[0]->workflowVersion());
        self::assertSame('abc123', $runs[0]->workflowRevision());
        self::assertSame('first input token=[REDACTED]', $runs[0]->inputSummary());
        self::assertStringContainsString('Workflow: developer', (string) $runs[0]->contextSnapshot());
        self::assertStringContainsString('Workflow version: v1', (string) $runs[0]->contextSnapshot());
        self::assertStringContainsString('Workflow revision: abc123', (string) $runs[0]->contextSnapshot());
        self::assertStringContainsString('Thread messages:', (string) $runs[0]->contextSnapshot());
        self::assertStringContainsString('password=[REDACTED]', (string) $runs[0]->contextSnapshot());
        self::assertStringNotContainsString('hunter2', (string) $runs[0]->contextSnapshot());
        self::assertSame('second input', $runs[1]->inputSummary());
        self::assertStringContainsString('Prior run summaries:', (string) $runs[1]->contextSnapshot());
        self::assertStringContainsString('first input token=[REDACTED]', (string) $runs[1]->contextSnapshot());
        self::assertStringContainsString('Bearer [REDACTED]', (string) $runs[1]->contextSnapshot());
        self::assertStringContainsString('Explicit global memories: none configured.', (string) $runs[1]->contextSnapshot());
    }
}
