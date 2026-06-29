<?php

namespace App\Tests\AgentTag\Memory;

use App\AgentTag\Configuration\AgentTagSettings;
use App\AgentTag\Memory\GlobalMemoryCommandContext;
use App\AgentTag\Memory\GlobalMemoryCommandHandler;
use App\AgentTag\Memory\GlobalMemoryService;
use App\Entity\GlobalMemory;
use App\Tests\RefreshDatabaseTrait;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class GlobalMemoryCommandHandlerTest extends KernelTestCase
{
    use RefreshDatabaseTrait;

    #[\Override]
    protected function setUp(): void
    {
        self::bootKernel();
        $this->refreshDatabase();
    }

    public function testItStoresListsAndDeletesExplicitMemories(): void
    {
        $handler = $this->handler();

        self::assertSame(
            'Stored global memory #1.',
            $handler->handle('@Codex remember that code reviews should stay concise', $this->context()),
        );

        $memories = $this->memoryService()->all();
        self::assertCount(1, $memories);
        self::assertSame('code reviews should stay concise', $memories[0]->content());
        self::assertSame('mattermost', $memories[0]->sourcePlatform());
        self::assertSame('user-1', $memories[0]->createdBy());

        self::assertSame(
            "Explicit global memories:\n- #1: code reviews should stay concise",
            $handler->handle('@Codex memories', $this->context()),
        );
        self::assertSame('Deleted global memory #1.', $handler->handle('@Codex delete memory #1', $this->context()));
        self::assertSame(0, $this->entityCount(GlobalMemory::class));
    }

    public function testItRefusesSecretOnlyMemoriesAndRedactsSensitiveValues(): void
    {
        $handler = $this->handler();

        self::assertSame(
            'I did not store that memory because it only contains a sensitive value.',
            $handler->handle('@Codex remember token=linear-secret', $this->context()),
        );
        self::assertSame(0, $this->entityCount(GlobalMemory::class));

        self::assertSame(
            'Stored global memory #1.',
            $handler->handle('@Codex remember staging uses token=linear-secret for tests', $this->context()),
        );

        $memories = $this->memoryService()->all();
        self::assertCount(1, $memories);
        self::assertSame('staging uses token=[REDACTED] for tests', $memories[0]->content());
    }

    public function testItIgnoresNonMemoryMessages(): void
    {
        self::assertNull($this->handler()->handle('@Codex implement OPE-1115', $this->context()));
    }

    private function handler(): GlobalMemoryCommandHandler
    {
        return new GlobalMemoryCommandHandler(
            new AgentTagSettings('@Codex', '/tmp/workspace', ''),
            $this->memoryService(),
        );
    }

    private function context(): GlobalMemoryCommandContext
    {
        return new GlobalMemoryCommandContext('mattermost', 'user-1', 'thread-1', 'message-1');
    }

    private function memoryService(): GlobalMemoryService
    {
        $service = static::getContainer()->get(GlobalMemoryService::class);
        self::assertInstanceOf(GlobalMemoryService::class, $service);

        return $service;
    }

    /**
     * @param class-string $entityClass
     */
    private function entityCount(string $entityClass): int
    {
        return count($this->entityManager()->getRepository($entityClass)->findAll());
    }

    private function entityManager(): EntityManagerInterface
    {
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $entityManager);

        return $entityManager;
    }
}
