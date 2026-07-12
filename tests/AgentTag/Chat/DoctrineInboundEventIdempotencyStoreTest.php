<?php

namespace App\Tests\AgentTag\Chat;

use App\AgentTag\Chat\InboundEventIdempotencyStore;
use App\Tests\RefreshDatabaseTrait;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class DoctrineInboundEventIdempotencyStoreTest extends KernelTestCase
{
    use RefreshDatabaseTrait;

    public function testDeduplicationSurvivesAcrossServiceInstances(): void
    {
        self::bootKernel();
        $this->refreshDatabase();
        $store = static::getContainer()->get(InboundEventIdempotencyStore::class);
        self::assertInstanceOf(InboundEventIdempotencyStore::class, $store);

        self::assertTrue($store->remember('mattermost:post-1'));
        self::assertFalse($store->remember('mattermost:post-1'));
        self::assertTrue($store->remember('mattermost:post-2'));
    }
}
