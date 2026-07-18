<?php

namespace App\Tests\AgentTag\Run;

use App\AgentTag\Run\AgentRunExecutionLock;
use PHPUnit\Framework\TestCase;

final class AgentRunExecutionLockTest extends TestCase
{
    public function testItRejectsASecondProcessLeaseForTheSameRun(): void
    {
        $lock = new AgentRunExecutionLock();
        $firstLease = $lock->acquire(987654321);

        self::assertNotNull($firstLease);
        self::assertNull((new AgentRunExecutionLock())->acquire(987654321));

        $firstLease->release();
    }

    public function testItCanBeAcquiredAgainAfterRelease(): void
    {
        $firstLease = (new AgentRunExecutionLock())->acquire(987654322);

        self::assertNotNull($firstLease);
        $firstLease->release();
        $secondLease = (new AgentRunExecutionLock())->acquire(987654322);

        self::assertNotNull($secondLease);
        $secondLease->release();
    }
}
