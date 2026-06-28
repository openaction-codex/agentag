<?php

namespace App\Tests\AgentTag\Mattermost;

use App\AgentTag\Mattermost\MattermostApiSettings;
use App\AgentTag\Mattermost\MattermostApiThreadContextProvider;
use App\AgentTag\Mattermost\MattermostInboundEvent;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class MattermostApiThreadContextProviderTest extends TestCase
{
    public function testItFetchesRootPostAndRecentThreadRepliesWhenConfigured(): void
    {
        $provider = new MattermostApiThreadContextProvider(
            new MockHttpClient(new MockResponse((string) json_encode([
                'order' => ['root-id', 'reply-1', 'reply-2', 'reply-3'],
                'posts' => [
                    'root-id' => ['message' => 'root text', 'user_id' => 'root-user'],
                    'reply-1' => ['message' => 'old reply', 'user_id' => 'reply-user-1'],
                    'reply-2' => ['message' => 'recent reply', 'user_id' => 'reply-user-2'],
                    'reply-3' => ['message' => 'latest reply', 'user_id' => 'reply-user-3'],
                ],
            ]))),
            new MattermostApiSettings('https://mattermost.example.test', 'bot-token', 3),
        );

        $context = $provider->contextFor($this->threadReplyEvent());
        $messages = $context->messages();

        self::assertCount(3, $messages);
        self::assertSame('root text', $messages[0]->text());
        self::assertSame('recent reply', $messages[1]->text());
        self::assertSame('latest reply', $messages[2]->text());
    }

    public function testItFallsBackToTheInboundMessageWhenMattermostApiIsNotConfigured(): void
    {
        $provider = new MattermostApiThreadContextProvider(
            new MockHttpClient(),
            new MattermostApiSettings('', '', 20),
        );

        $context = $provider->contextFor($this->threadReplyEvent());
        $messages = $context->messages();

        self::assertCount(1, $messages);
        self::assertSame('reply-id', $messages[0]->externalId());
        self::assertSame('@Codex continue', $messages[0]->text());
    }

    private function threadReplyEvent(): MattermostInboundEvent
    {
        return new MattermostInboundEvent(
            'reply-id',
            '@Codex continue',
            'reply-id',
            'root-id',
            'channel-id',
            'O',
            'team-id',
            'user-id',
            '',
        );
    }
}
