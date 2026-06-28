<?php

namespace App\Tests\AgentTag\Slack;

use App\AgentTag\Slack\SlackInboundEvent;
use App\AgentTag\Slack\SlackSessionMapper;
use PHPUnit\Framework\TestCase;

final class SlackSessionMapperTest extends TestCase
{
    public function testItUsesThreadTimestampWhenPresent(): void
    {
        $session = (new SlackSessionMapper())->map($this->event(threadTs: '1700000000.000001'));

        self::assertSame('1700000000.000001', $session->threadId());
        self::assertSame('slack:T123:C123:1700000000.000001', $session->key());
    }

    public function testItUsesEventTimestampForRootMessages(): void
    {
        $session = (new SlackSessionMapper())->map($this->event(threadTs: ''));

        self::assertSame('1700000000.000000', $session->threadId());
    }

    private function event(string $threadTs): SlackInboundEvent
    {
        return new SlackInboundEvent(
            'Ev123',
            '@Codex help',
            '1700000000.000000',
            $threadTs,
            'C123',
            'T123',
            'U123',
            '',
        );
    }
}
