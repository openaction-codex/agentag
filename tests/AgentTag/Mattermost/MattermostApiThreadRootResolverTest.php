<?php

namespace App\Tests\AgentTag\Mattermost;

use App\AgentTag\Mattermost\MattermostApiSettings;
use App\AgentTag\Mattermost\MattermostApiThreadRootResolver;
use App\AgentTag\Mattermost\MattermostInboundEvent;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class MattermostApiThreadRootResolverTest extends TestCase
{
    public function testItLoadsTheCanonicalRootWhenTheWebhookOmitsRootId(): void
    {
        $resolver = new MattermostApiThreadRootResolver(
            new MockHttpClient(new MockResponse((string) json_encode([
                'id' => 'reply-id',
                'root_id' => 'root-id',
                'channel_id' => 'channel-id',
            ]))),
            new MattermostApiSettings('https://mattermost.example.test', 'bot-token', 20),
        );

        self::assertSame('root-id', $resolver->rootIdFor($this->event()));
    }

    public function testItKeepsTheWebhookRootWithoutCallingTheApi(): void
    {
        $resolver = new MattermostApiThreadRootResolver(
            new MockHttpClient(static fn (): MockResponse => throw new \LogicException('The API must not be called.')),
            new MattermostApiSettings('https://mattermost.example.test', 'bot-token', 20),
        );

        self::assertSame('webhook-root', $resolver->rootIdFor($this->event('webhook-root')));
    }

    public function testItFallsBackToThePostIdWhenTheApiIsUnavailable(): void
    {
        $resolver = new MattermostApiThreadRootResolver(
            new MockHttpClient(),
            new MattermostApiSettings('', '', 20),
        );

        self::assertSame('reply-id', $resolver->rootIdFor($this->event()));
    }

    private function event(string $rootId = ''): MattermostInboundEvent
    {
        return new MattermostInboundEvent(
            'reply-id',
            '@Codex continue',
            'reply-id',
            $rootId,
            'channel-id',
            'O',
            'team-id',
            'user-id',
            '',
        );
    }
}
