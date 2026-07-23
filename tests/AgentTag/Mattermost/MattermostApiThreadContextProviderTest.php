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
                'order' => ['root-id', 'reply-1', 'reply-2', 'reply-id'],
                'posts' => [
                    'root-id' => ['message' => 'root text', 'user_id' => 'root-user', 'create_at' => 100],
                    'reply-1' => ['message' => 'old reply', 'user_id' => 'reply-user-1', 'create_at' => 200],
                    'reply-2' => ['message' => 'recent reply', 'user_id' => 'reply-user-2', 'create_at' => 300],
                    'reply-id' => ['message' => '@Codex continue', 'user_id' => 'user-id', 'create_at' => 400],
                ],
            ]))),
            new MattermostApiSettings('https://mattermost.example.test', 'bot-token', 3),
        );

        $context = $provider->contextFor($this->threadReplyEvent(), 'root-id');
        $messages = $context->messages();

        self::assertCount(3, $messages);
        self::assertSame('root text', $messages[0]->text());
        self::assertSame('recent reply', $messages[1]->text());
        self::assertSame('@Codex continue', $messages[2]->text());
    }

    public function testItUsesCanonicalRootAndSortsRepliesByCreationTime(): void
    {
        $provider = new MattermostApiThreadContextProvider(
            new MockHttpClient(new MockResponse((string) json_encode([
                'order' => ['reply-id', 'old-reply', 'root-id', 'recent-reply'],
                'posts' => [
                    'reply-id' => ['message' => '@Codex answer the import question', 'user_id' => 'user-id', 'create_at' => 400],
                    'old-reply' => ['message' => '10h?', 'user_id' => 'other-user', 'create_at' => 200],
                    'root-id' => ['message' => 'Import failure report', 'user_id' => 'system', 'create_at' => 100],
                    'recent-reply' => ['message' => 'paymentProviderDetails is invalid', 'user_id' => 'developer', 'create_at' => 300],
                ],
            ]))),
            new MattermostApiSettings('https://mattermost.example.test', 'bot-token', 4),
        );

        $context = $provider->contextFor($this->threadReplyEvent('@Codex answer the import question'), 'root-id');

        self::assertSame(
            ['Import failure report', '10h?', 'paymentProviderDetails is invalid', '@Codex answer the import question'],
            array_map(static fn ($message): string => $message->text(), $context->messages()),
        );
    }

    public function testItFallsBackToTheInboundMessageWhenMattermostApiIsNotConfigured(): void
    {
        $provider = new MattermostApiThreadContextProvider(
            new MockHttpClient(),
            new MattermostApiSettings('', '', 20),
        );

        $context = $provider->contextFor($this->threadReplyEvent(), 'root-id');
        $messages = $context->messages();

        self::assertCount(1, $messages);
        self::assertSame('reply-id', $messages[0]->externalId());
        self::assertSame('@Codex continue', $messages[0]->text());
    }

    private function threadReplyEvent(string $text = '@Codex continue'): MattermostInboundEvent
    {
        return new MattermostInboundEvent(
            'reply-id',
            $text,
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
