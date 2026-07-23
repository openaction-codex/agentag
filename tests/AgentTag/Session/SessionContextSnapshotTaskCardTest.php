<?php

namespace App\Tests\AgentTag\Session;

use App\AgentTag\Agent\AgentProfile;
use App\AgentTag\Session\ChatThreadContext;
use App\AgentTag\Session\ChatThreadMessage;
use App\AgentTag\Session\SessionContextSnapshotBuilder;
use App\Entity\ChatSession;
use PHPUnit\Framework\TestCase;

final class SessionContextSnapshotTaskCardTest extends TestCase
{
    public function testItExcludesAgentTaskCardsFromThreadHistory(): void
    {
        $snapshot = (new SessionContextSnapshotBuilder(2000))->build(
            new ChatSession('session', 'team', 'channel', 'thread', new \DateTimeImmutable()),
            new ChatThreadContext([
                new ChatThreadMessage('root', 'system', 'Import failed.'),
                new ChatThreadMessage('card', 'agent', '> ✅ **Request received**'),
                new ChatThreadMessage('reply', 'user', '@agent explain the import fields'),
            ]),
            [],
            new AgentProfile('agent', '/workspace', null, 'workspace-write', 60),
        );

        self::assertStringContainsString('Import failed.', $snapshot);
        self::assertStringContainsString('@agent explain the import fields', $snapshot);
        self::assertStringNotContainsString('Request received', $snapshot);
    }
}
