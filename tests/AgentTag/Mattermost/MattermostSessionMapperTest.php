<?php

namespace App\Tests\AgentTag\Mattermost;

use App\AgentTag\Mattermost\MattermostInboundEvent;
use App\AgentTag\Mattermost\MattermostSessionMapper;
use App\AgentTag\Mattermost\MattermostThreadRootResolver;
use PHPUnit\Framework\TestCase;

final class MattermostSessionMapperTest extends TestCase
{
    public function testItUsesRootIdForThreadReplies(): void
    {
        $session = $this->mapper()->map($this->event(rootId: 'root-post'));

        self::assertSame('root-post', $session->threadId());
        self::assertSame('mattermost:team:channel:root-post', $session->key());
    }

    public function testItResolvesTheCanonicalRootWhenTheWebhookOmitsIt(): void
    {
        $session = $this->mapper('canonical-root')->map($this->event(rootId: '', channelType: 'O'));

        self::assertSame('canonical-root', $session->threadId());
        self::assertSame('mattermost:team:channel:canonical-root', $session->key());
    }

    public function testItUsesChannelIdForDirectMessagesWithoutAThreadRoot(): void
    {
        $session = $this->mapper('ignored-root')->map($this->event(rootId: '', channelType: 'D'));

        self::assertSame('channel', $session->threadId());
    }

    private function mapper(string $resolvedRoot = 'post'): MattermostSessionMapper
    {
        return new MattermostSessionMapper(new class($resolvedRoot) implements MattermostThreadRootResolver {
            public function __construct(private readonly string $resolvedRoot)
            {
            }

            #[\Override]
            public function rootIdFor(MattermostInboundEvent $event): string
            {
                return '' !== $event->rootId() ? $event->rootId() : $this->resolvedRoot;
            }
        });
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
