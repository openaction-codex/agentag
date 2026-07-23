<?php

namespace App\Tests\Controller;

use App\AgentTag\Mattermost\TaskCardRenderer;
use App\Entity\AgentRun;
use App\Entity\ChatSession;
use App\Tests\RefreshDatabaseTrait;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class MattermostActionControllerTest extends WebTestCase
{
    use RefreshDatabaseTrait;

    public function testRequesterCanCancelAndReceiveAnUpdatedCard(): void
    {
        $client = static::createClient();
        $this->refreshDatabase();
        $run = $this->persistRun(AgentRun::STATUS_RUNNING);
        $renderer = static::getContainer()->get(TaskCardRenderer::class);
        self::assertInstanceOf(TaskCardRenderer::class, $renderer);

        $client->jsonRequest('POST', '/integrations/mattermost/action', [
            'user_id' => 'requester',
            'user_name' => 'thomas',
            'context' => [
                'run_id' => $run->id(),
                'action' => 'cancel',
                'signature' => $renderer->signature((int) $run->id(), 'cancel'),
            ],
        ]);

        self::assertResponseIsSuccessful();
        $payload = json_decode((string) $client->getResponse()->getContent(), true);
        self::assertIsArray($payload);
        $ephemeral = $payload['ephemeral_text'] ?? null;
        self::assertIsString($ephemeral);
        self::assertStringContainsString('Stopping after the current command', $ephemeral);
        $update = $payload['update'] ?? null;
        self::assertIsArray($update);
        $message = $update['message'] ?? null;
        self::assertIsString($message);
        self::assertStringContainsString('Stopping after the current command', $message);
        $props = $update['props'] ?? null;
        self::assertIsArray($props);
        self::assertSame([], $props['attachments'] ?? null);
        $this->entityManager()->refresh($run);
        self::assertSame(AgentRun::STATUS_INTERRUPT_REQUESTED, $run->status());
        self::assertSame(AgentRun::INTERRUPT_CANCEL, $run->interruptionKind());
        self::assertSame('thomas', $run->stoppedByName());
    }

    public function testAnyMattermostUserCanStopAnotherUsersTask(): void
    {
        $client = static::createClient();
        $this->refreshDatabase();
        $run = $this->persistRun(AgentRun::STATUS_RUNNING);
        $renderer = static::getContainer()->get(TaskCardRenderer::class);
        self::assertInstanceOf(TaskCardRenderer::class, $renderer);

        $client->jsonRequest('POST', '/integrations/mattermost/action', [
            'user_id' => 'different-user',
            'user_name' => 'baptiste',
            'context' => [
                'run_id' => $run->id(),
                'action' => 'cancel',
                'signature' => $renderer->signature((int) $run->id(), 'cancel'),
            ],
        ]);

        self::assertResponseIsSuccessful();
        $this->entityManager()->refresh($run);
        self::assertSame(AgentRun::STATUS_INTERRUPT_REQUESTED, $run->status());
        self::assertSame('baptiste', $run->stoppedByName());
        self::assertStringContainsString('Stop requested by @baptiste.', (string) $client->getResponse()->getContent());
    }

    public function testStopImmediatelyFinishesATaskWaitingForAutomaticRetry(): void
    {
        $client = static::createClient();
        $this->refreshDatabase();
        $run = $this->persistRun(AgentRun::STATUS_ACCEPTED);
        $run->updateStage('The stage failed and will be retried automatically.');
        $this->entityManager()->flush();
        $renderer = static::getContainer()->get(TaskCardRenderer::class);
        self::assertInstanceOf(TaskCardRenderer::class, $renderer);

        $client->jsonRequest('POST', '/integrations/mattermost/action', [
            'user_id' => 'requester',
            'context' => [
                'run_id' => $run->id(),
                'action' => 'cancel',
                'signature' => $renderer->signature((int) $run->id(), 'cancel'),
            ],
        ]);

        self::assertResponseIsSuccessful();
        $payload = json_decode((string) $client->getResponse()->getContent(), true);
        self::assertIsArray($payload);
        $ephemeral = $payload['ephemeral_text'] ?? null;
        self::assertIsString($ephemeral);
        self::assertStringContainsString('Stopped the task', $ephemeral);
        $update = $payload['update'] ?? null;
        self::assertIsArray($update);
        $message = $update['message'] ?? null;
        self::assertIsString($message);
        self::assertStringContainsString('■ Stopped', $message);
        self::assertStringNotContainsString('→ Stopping after the current command', $message);
        $this->entityManager()->refresh($run);
        self::assertSame(AgentRun::STATUS_INTERRUPTED, $run->status());
    }

    public function testDetailsAreEphemeralAndSigned(): void
    {
        $client = static::createClient();
        $this->refreshDatabase();
        $run = $this->persistRun(AgentRun::STATUS_RUNNING);
        $run->recordRunnerResult(AgentRun::STATUS_RUNNING, '', 'stdout: composer test passed', '/tmp/task', [], 0, null);
        $this->entityManager()->flush();
        $renderer = static::getContainer()->get(TaskCardRenderer::class);
        self::assertInstanceOf(TaskCardRenderer::class, $renderer);

        $client->jsonRequest('POST', '/integrations/mattermost/action', [
            'user_id' => 'requester',
            'context' => [
                'run_id' => $run->id(),
                'action' => 'details',
                'signature' => $renderer->signature((int) $run->id(), 'details'),
            ],
        ]);

        self::assertResponseIsSuccessful();
        self::assertStringContainsString('composer test passed', (string) $client->getResponse()->getContent());
    }

    private function persistRun(string $status): AgentRun
    {
        $session = new ChatSession('mattermost:team:channel:thread', 'team', 'channel', 'thread', new \DateTimeImmutable());
        $run = new AgentRun($session, $status, new \DateTimeImmutable(), requesterId: 'requester', workspacePath: '/tmp/task');
        $run->initializeTask('Fix billing tests', 'Workspace ready', 'thomas', new \DateTimeImmutable('+1 day'), 2, 60, 'milestones');
        $run->assignTaskPost('task-post');
        $this->entityManager()->persist($session);
        $this->entityManager()->persist($run);
        $this->entityManager()->flush();

        return $run;
    }

    private function entityManager(): EntityManagerInterface
    {
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $entityManager);

        return $entityManager;
    }
}
