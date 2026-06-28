<?php

namespace App\Tests\AgentTag\Mattermost;

use App\AgentTag\Mattermost\MattermostInboundEvent;
use App\AgentTag\Mattermost\MattermostSessionMapper;
use PHPUnit\Framework\TestCase;

final class MattermostSessionMapperTest extends TestCase
{
    public function testItUsesRootIdForThreadReplies(): void
    {
        $session = (new MattermostSessionMapper())->map($this->event(rootId: 'root-post'));

        self::assertSame('root-post', $session->threadId());
        self::assertSame('mattermost:team:channel:root-post', $session->key());
    }

    public function testItUsesPostIdForRootChannelMessages(): void
    {
        $session = (new MattermostSessionMapper())->map($this->event(rootId: '', channelType: 'O'));

        self::assertSame('post', $session->threadId());
    }

    public function testItUsesChannelIdForDirectMessagesWithoutAThreadRoot(): void
    {
        $session = (new MattermostSessionMapper())->map($this->event(rootId: '', channelType: 'D'));

        self::assertSame('channel', $session->threadId());
    }

    private function event(string $rootId, string $channelType = 'O'): MattermostInboundEvent
    {
        return new MattermostInboundEvent(
            'post',
            '@Codex help',
            'post',
            $rootId,
            'channel',
            $channelType,
            'team',
            'user',
            '',
        );
    }
}
