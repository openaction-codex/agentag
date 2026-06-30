<?php

namespace App\Tests\AgentTag\Run;

use App\AgentTag\Run\AgentRunTurnGate;
use App\Entity\AgentRun;
use App\Entity\ChatSession;
use App\Tests\RefreshDatabaseTrait;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class AgentRunTurnGateTest extends KernelTestCase
{
    use RefreshDatabaseTrait;

    #[\Override]
    protected function setUp(): void
    {
        self::bootKernel();
        $this->refreshDatabase();
    }

    public function testItAllowsARunWhenNoEarlierRunIsActiveInTheSameThread(): void
    {
        $entityManager = $this->entityManager();
        $session = new ChatSession('mattermost:team:channel:thread', 'mattermost', 'team', 'channel', 'thread', new \DateTimeImmutable());
        $otherSession = new ChatSession('mattermost:team:channel:other', 'mattermost', 'team', 'channel', 'other', new \DateTimeImmutable());
        $otherActiveRun = new AgentRun($otherSession, AgentRun::STATUS_RUNNING, new \DateTimeImmutable());
        $run = new AgentRun($session, AgentRun::STATUS_ACCEPTED, new \DateTimeImmutable());
        $entityManager->persist($session);
        $entityManager->persist($otherSession);
        $entityManager->persist($otherActiveRun);
        $entityManager->persist($run);
        $entityManager->flush();

        $gate = new AgentRunTurnGate($entityManager);

        self::assertTrue($gate->waitForTurn($run, 1));
    }

    public function testItWaitsBehindEarlierActiveRunsInTheSameThread(): void
    {
        $entityManager = $this->entityManager();
        $session = new ChatSession('mattermost:team:channel:thread', 'mattermost', 'team', 'channel', 'thread', new \DateTimeImmutable());
        $earlierActiveRun = new AgentRun($session, AgentRun::STATUS_RUNNING, new \DateTimeImmutable());
        $run = new AgentRun($session, AgentRun::STATUS_ACCEPTED, new \DateTimeImmutable());
        $entityManager->persist($session);
        $entityManager->persist($earlierActiveRun);
        $entityManager->persist($run);
        $entityManager->flush();
        $heartbeats = 0;

        $gate = new AgentRunTurnGate($entityManager);

        self::assertFalse($gate->waitForTurn($run, 1, static function () use (&$heartbeats): void {
            ++$heartbeats;
        }));
        self::assertGreaterThan(0, $heartbeats);
    }

    private function entityManager(): EntityManagerInterface
    {
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $entityManager);

        return $entityManager;
    }
}
