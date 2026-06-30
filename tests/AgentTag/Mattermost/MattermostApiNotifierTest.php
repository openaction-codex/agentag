<?php

namespace App\Tests\AgentTag\Mattermost;

use App\AgentTag\Mattermost\MattermostApiNotifier;
use App\AgentTag\Mattermost\MattermostApiSettings;
use App\AgentTag\Mattermost\MattermostInboundEvent;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;
use Psr\Log\LogLevel;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class MattermostApiNotifierTest extends TestCase
{
    public function testItJoinsPublicChannelsAndRetriesDeniedTypingIndicators(): void
    {
        $requests = [];
        $logger = new TraceableLogger();
        $responses = [
            new MockResponse('{"id":"api.context.permissions.app_error","message":"You do not have the appropriate permissions."}', ['http_code' => 403]),
            new MockResponse('{"id":"bot-user-id"}'),
            new MockResponse('{"channel_id":"channel-id","user_id":"bot-user-id","roles":"channel_user"}', ['http_code' => 201]),
            new MockResponse('{}'),
        ];
        $httpClient = new MockHttpClient(static function (string $method, string $url, array $options) use (&$requests, &$responses): MockResponse {
            $requests[] = [
                'method' => $method,
                'url' => $url,
                'body' => self::requestBody($options),
            ];

            return array_shift($responses) ?? new MockResponse('', ['http_code' => 500]);
        });
        $notifier = new MattermostApiNotifier(
            $httpClient,
            new MattermostApiSettings('https://mattermost.example.test', 'bot-token', 20),
            $logger,
        );

        $notifier->showTyping($this->publicChannelEvent());

        self::assertSame([
            ['POST', 'https://mattermost.example.test/api/v4/users/me/typing'],
            ['GET', 'https://mattermost.example.test/api/v4/users/me'],
            ['POST', 'https://mattermost.example.test/api/v4/channels/channel-id/members'],
            ['POST', 'https://mattermost.example.test/api/v4/users/me/typing'],
        ], array_map(
            static fn (array $request): array => [$request['method'], $request['url']],
            $requests,
        ));
        self::assertSame(['channel_id' => 'channel-id', 'parent_id' => 'post-id'], $requests[0]['body']);
        self::assertNull($requests[1]['body']);
        self::assertSame(['user_id' => 'bot-user-id'], $requests[2]['body']);
        self::assertSame(['channel_id' => 'channel-id', 'parent_id' => 'post-id'], $requests[3]['body']);
        self::assertSame(LogLevel::DEBUG, $logger->records[0]['level']);
        self::assertSame('Mattermost bot joined public channel before sending typing indicator.', $logger->records[0]['message']);
        self::assertSame('channel-id', $logger->records[0]['context']['channel_id']);
    }

    public function testItLogsMattermostResponseBodiesForFailedRequests(): void
    {
        $logger = new TraceableLogger();
        $notifier = new MattermostApiNotifier(
            new MockHttpClient(new MockResponse('{"id":"api.context.invalid_param.app_error","message":"Invalid payload"}', ['http_code' => 400])),
            new MattermostApiSettings('https://mattermost.example.test', 'bot-token', 20),
            $logger,
        );

        $notifier->postProgress($this->privateChannelEvent(), 'Done');

        self::assertSame(LogLevel::WARNING, $logger->records[0]['level']);
        self::assertSame('Mattermost API request failed.', $logger->records[0]['message']);
        self::assertSame(400, $logger->records[0]['context']['status_code']);
        $responseBody = $logger->records[0]['context']['response_body'] ?? null;
        self::assertIsString($responseBody);
        self::assertStringContainsString('Invalid payload', $responseBody);
    }

    public function testItFallsBackToChannelPostsWhenMattermostRejectsTheThreadRoot(): void
    {
        $requests = [];
        $logger = new TraceableLogger();
        $responses = [
            new MockResponse('{"id":"api.post.create_post.root_id.app_error","message":"Invalid RootId parameter."}', ['http_code' => 400]),
            new MockResponse('{"id":"api.context.permissions.app_error","message":"You do not have the appropriate permissions."}', ['http_code' => 403]),
            new MockResponse('{"id":"fallback-post-id"}'),
        ];
        $httpClient = new MockHttpClient(static function (string $method, string $url, array $options) use (&$requests, &$responses): MockResponse {
            $requests[] = [
                'method' => $method,
                'url' => $url,
                'body' => self::requestBody($options),
            ];

            return array_shift($responses) ?? new MockResponse('', ['http_code' => 500]);
        });
        $notifier = new MattermostApiNotifier(
            $httpClient,
            new MattermostApiSettings('https://mattermost.example.test', 'bot-token', 20),
            $logger,
        );

        $notifier->postProgress($this->publicChannelEvent(), 'Done');

        self::assertSame([
            ['POST', 'https://mattermost.example.test/api/v4/posts'],
            ['GET', 'https://mattermost.example.test/api/v4/posts/post-id'],
            ['POST', 'https://mattermost.example.test/api/v4/posts'],
        ], array_map(
            static fn (array $request): array => [$request['method'], $request['url']],
            $requests,
        ));
        self::assertSame(['channel_id' => 'channel-id', 'message' => 'Done', 'root_id' => 'post-id'], $requests[0]['body']);
        self::assertNull($requests[1]['body']);
        self::assertSame(['channel_id' => 'channel-id', 'message' => 'Done'], $requests[2]['body']);
        self::assertSame('Mattermost API request failed.', $logger->records[0]['message']);
        self::assertSame('resolve_thread_root', $logger->records[0]['context']['operation']);
        self::assertSame('Mattermost rejected a threaded post root; retrying as a channel post.', $logger->records[1]['message']);
        self::assertSame('post-id', $logger->records[1]['context']['root_id']);
    }

    public function testItResolvesTheThreadRootFromTheSourcePostWhenMattermostRejectsTheStoredRoot(): void
    {
        $requests = [];
        $logger = new TraceableLogger();
        $responses = [
            new MockResponse('{"id":"api.post.create_post.root_id.app_error","message":"Invalid RootId parameter."}', ['http_code' => 400]),
            new MockResponse('{"id":"reply-id","root_id":"real-root-id","channel_id":"channel-id"}'),
            new MockResponse('{"id":"threaded-post-id"}'),
        ];
        $httpClient = new MockHttpClient(static function (string $method, string $url, array $options) use (&$requests, &$responses): MockResponse {
            $requests[] = [
                'method' => $method,
                'url' => $url,
                'body' => self::requestBody($options),
            ];

            return array_shift($responses) ?? new MockResponse('', ['http_code' => 500]);
        });
        $notifier = new MattermostApiNotifier(
            $httpClient,
            new MattermostApiSettings('https://mattermost.example.test', 'bot-token', 20),
            $logger,
        );

        $notifier->postProgress($this->threadReplyEvent(), 'Done');

        self::assertSame([
            ['POST', 'https://mattermost.example.test/api/v4/posts'],
            ['GET', 'https://mattermost.example.test/api/v4/posts/reply-id'],
            ['POST', 'https://mattermost.example.test/api/v4/posts'],
        ], array_map(
            static fn (array $request): array => [$request['method'], $request['url']],
            $requests,
        ));
        self::assertSame(['channel_id' => 'channel-id', 'message' => 'Done', 'root_id' => 'bad-root-id'], $requests[0]['body']);
        self::assertNull($requests[1]['body']);
        self::assertSame(['channel_id' => 'channel-id', 'message' => 'Done', 'root_id' => 'real-root-id'], $requests[2]['body']);
        self::assertSame('Mattermost rejected a threaded post root; retrying with the root resolved from the source post.', $logger->records[0]['message']);
        self::assertSame('bad-root-id', $logger->records[0]['context']['root_id']);
        self::assertSame('real-root-id', $logger->records[0]['context']['resolved_root_id']);
    }

    /**
     * @param array<mixed, mixed> $options
     *
     * @return array<string, mixed>|null
     */
    private static function requestBody(array $options): ?array
    {
        $body = $options['body'] ?? null;
        if (!is_string($body) || '' === $body) {
            return null;
        }

        $decoded = json_decode($body, true);

        if (!is_array($decoded)) {
            return null;
        }

        $normalized = [];
        foreach ($decoded as $key => $value) {
            if (!is_string($key)) {
                return null;
            }

            $normalized[$key] = $value;
        }

        return $normalized;
    }

    private function publicChannelEvent(): MattermostInboundEvent
    {
        return new MattermostInboundEvent(
            'event-id',
            '@Codex help',
            'post-id',
            '',
            'channel-id',
            'O',
            'team-id',
            'user-id',
            '',
        );
    }

    private function privateChannelEvent(): MattermostInboundEvent
    {
        return new MattermostInboundEvent(
            'event-id',
            '@Codex help',
            'post-id',
            '',
            'channel-id',
            'P',
            'team-id',
            'user-id',
            '',
        );
    }

    private function threadReplyEvent(): MattermostInboundEvent
    {
        return new MattermostInboundEvent(
            'event-id',
            '@Codex help',
            'reply-id',
            'bad-root-id',
            'channel-id',
            'O',
            'team-id',
            'user-id',
            '',
        );
    }
}

/**
 * @phpstan-type LogRecord array{level: mixed, message: string|\Stringable, context: array<string, mixed>}
 */
final class TraceableLogger extends AbstractLogger
{
    /**
     * @var list<array{level: mixed, message: string|\Stringable, context: array<string, mixed>}>
     */
    public array $records = [];

    /**
     * @param array<string, mixed> $context
     */
    #[\Override]
    public function log($level, string|\Stringable $message, array $context = []): void
    {
        $this->records[] = [
            'level' => $level,
            'message' => $message,
            'context' => $context,
        ];
    }
}
