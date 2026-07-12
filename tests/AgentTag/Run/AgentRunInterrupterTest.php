<?php

namespace App\Tests\AgentTag\Run;

use App\AgentTag\Chat\ChatSessionReference;
use App\AgentTag\Run\AgentRunInterrupter;
use App\AgentTag\Run\RunEventRecorder;
use App\AgentTag\Security\SensitiveTextRedactor;
use App\Entity\AgentRun;
use App\Entity\ChatSession;
use App\Entity\RunEvent;
use App\Tests\RefreshDatabaseTrait;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class AgentRunInterrupterTest extends KernelTestCase
{
    use RefreshDatabaseTrait;

    #[\Override]
    protected function setUp(): void
    {
        self::bootKernel();
        $this->refreshDatabase();
    }

    public function testItRequestsInterruptionForActiveRunsInTheSameSession(): void
    {
        $entityManager = $this->entityManager();
        $session = new ChatSession('mattermost:team:channel:thread', 'team', 'channel', 'thread', new \DateTimeImmutable());
        $activeRun = new AgentRun($session, AgentRun::STATUS_RUNNING, new \DateTimeImmutable(), sourceEventId: 'old-post');
        $completedRun = new AgentRun($session, AgentRun::STATUS_COMPLETED, new \DateTimeImmutable(), sourceEventId: 'done-post');
        $entityManager->persist($session);
        $entityManager->persist($activeRun);
        $entityManager->persist($completedRun);
        $entityManager->flush();

        $interrupter = new AgentRunInterrupter(
            $entityManager,
            new RunEventRecorder($entityManager, new SensitiveTextRedactor(), new NullLogger()),
        );

        $interrupted = $interrupter->cancelActiveRun(
            new ChatSessionReference('team', 'channel', 'thread'),
            'new-post',
            'user',
        );

        self::assertSame($activeRun, $interrupted);
        self::assertSame(AgentRun::STATUS_INTERRUPT_REQUESTED, $activeRun->status());
        self::assertSame(AgentRun::INTERRUPT_CANCEL, $activeRun->interruptionKind());
        self::assertSame(AgentRun::STATUS_COMPLETED, $completedRun->status());
        $events = $entityManager->getRepository(RunEvent::class)->findBy(['run' => $activeRun]);
        self::assertCount(1, $events);
        self::assertSame(RunEvent::TYPE_CANCELLATION_REQUESTED, $events[0]->type());
    }

    public function testItQueuesSteeringOnTheSameActiveRun(): void
    {
        $entityManager = $this->entityManager();
        $session = new ChatSession('mattermost:team:channel:thread', 'team', 'channel', 'thread', new \DateTimeImmutable());
        $run = new AgentRun($session, AgentRun::STATUS_RUNNING, new \DateTimeImmutable());
        $entityManager->persist($session);
        $entityManager->persist($run);
        $entityManager->flush();
        $interrupter = new AgentRunInterrupter($entityManager);

        $steered = $interrupter->steerActiveRun(
            new ChatSessionReference('team', 'channel', 'thread'),
            'Focus on the backend.',
            'new-post',
            'user',
        );

        self::assertSame($run, $steered);
        self::assertSame('Focus on the backend.', $run->pendingSteering());
        self::assertSame(AgentRun::INTERRUPT_STEER, $run->interruptionKind());
        self::assertSame(AgentRun::STATUS_INTERRUPT_REQUESTED, $run->status());
    }

    private function entityManager(): EntityManagerInterface
    {
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $entityManager);

        return $entityManager;
    }
}
