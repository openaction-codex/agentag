<?php

namespace App\AgentTag\Mattermost;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class MattermostApiNotifier implements MattermostNotifier
{
    private ?string $botUserId = null;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly MattermostApiSettings $settings,
        private readonly ?LoggerInterface $logger = null,
    ) {
    }

    #[\Override]
    public function showTyping(MattermostInboundEvent $event): void
    {
        if (!$this->settings->enabled()) {
            return;
        }

        $payload = ['channel_id' => $event->channelId()];
        $rootId = $this->replyRootId($event);
        if ('' !== $rootId) {
            $payload['parent_id'] = $rootId;
        }

        $response = $this->requestWithChannelAccess($event, 'POST', '/api/v4/users/me/typing', $payload);
        if (null !== $response && $response['status_code'] >= 400) {
            $this->logFailedRequest('POST', '/api/v4/users/me/typing', $response, [
                'channel_id' => $event->channelId(),
                'channel_type' => $event->channelType(),
            ]);
        }
    }

    #[\Override]
    public function postProgress(MattermostInboundEvent $event, string $message): void
    {
        if (!$this->settings->enabled() || '' === trim($message)) {
            return;
        }

        $payload = [
            'channel_id' => $event->channelId(),
            'message' => trim($message),
        ];
        $rootId = $this->replyRootId($event);
        if ('' !== $rootId) {
            $payload['root_id'] = $rootId;
        }

        $response = $this->requestWithChannelAccess($event, 'POST', '/api/v4/posts', $payload);
        if (null === $response || $response['status_code'] < 400) {
            return;
        }

        if (isset($payload['root_id']) && $this->isInvalidRootIdResponse($response)) {
            $rootId = $payload['root_id'];

            $resolvedRootId = $this->resolveThreadRootId($event);
            if (null !== $resolvedRootId && $resolvedRootId !== $rootId) {
                $payload['root_id'] = $resolvedRootId;

                $this->logger?->warning('Mattermost rejected a threaded post root; retrying with the root resolved from the source post.', [
                    'channel_id' => $event->channelId(),
                    'channel_type' => $event->channelType(),
                    'root_id' => $rootId,
                    'resolved_root_id' => $resolvedRootId,
                    'source_post_id' => $event->postId(),
                    'status_code' => $response['status_code'],
                    'response_body' => $this->truncateResponseBody($response['body']),
                ]);

                $resolvedResponse = $this->requestWithChannelAccess($event, 'POST', '/api/v4/posts', $payload);
                if (null === $resolvedResponse || $resolvedResponse['status_code'] < 400) {
                    return;
                }

                $response = $resolvedResponse;
            }

            unset($payload['root_id']);

            $this->logger?->warning('Mattermost rejected a threaded post root; retrying as a channel post.', [
                'channel_id' => $event->channelId(),
                'channel_type' => $event->channelType(),
                'root_id' => $rootId,
                'status_code' => $response['status_code'],
                'response_body' => $this->truncateResponseBody($response['body']),
            ]);

            $fallbackResponse = $this->requestWithChannelAccess($event, 'POST', '/api/v4/posts', $payload);
            if (null === $fallbackResponse || $fallbackResponse['status_code'] < 400) {
                return;
            }

            $this->logFailedRequest('POST', '/api/v4/posts', $fallbackResponse, [
                'channel_id' => $event->channelId(),
                'channel_type' => $event->channelType(),
                'fallback_after_invalid_root_id' => true,
            ]);

            return;
        }

        $this->logFailedRequest('POST', '/api/v4/posts', $response, [
            'channel_id' => $event->channelId(),
            'channel_type' => $event->channelType(),
        ]);
    }

    /**
     * @param array<string, string> $payload
     *
     * @return array{status_code: int, body: string}|null
     */
    private function requestWithChannelAccess(MattermostInboundEvent $event, string $method, string $path, array $payload): ?array
    {
        $response = $this->request($method, $path, $payload);
        if (null === $response || $response['status_code'] < 400) {
            return $response;
        }

        if (403 === $response['status_code'] && 'O' === $event->channelType()) {
            $this->logger?->info('Mattermost API request failed for a public channel; attempting to join before retrying.', [
                'method' => $method,
                'path' => $path,
                'status_code' => $response['status_code'],
                'channel_id' => $event->channelId(),
            ]);

            if ($this->joinPublicChannel($event->channelId())) {
                $retryResponse = $this->request($method, $path, $payload);
                if (null === $retryResponse || $retryResponse['status_code'] < 400) {
                    return $retryResponse;
                }

                return $retryResponse;
            }
        }

        return $response;
    }

    private function joinPublicChannel(string $channelId): bool
    {
        $botUserId = $this->botUserId();
        if (null === $botUserId) {
            return false;
        }

        $path = sprintf('/api/v4/channels/%s/members', rawurlencode($channelId));
        $response = $this->request('POST', $path, ['user_id' => $botUserId]);
        if (null !== $response && $response['status_code'] < 400) {
            $this->logger?->info('Mattermost bot joined public channel before retrying request.', [
                'channel_id' => $channelId,
            ]);

            return true;
        }

        $this->logFailedRequest('POST', $path, $response, [
            'channel_id' => $channelId,
            'operation' => 'join_public_channel',
        ]);

        return false;
    }

    private function resolveThreadRootId(MattermostInboundEvent $event): ?string
    {
        if ('' === $event->postId()) {
            return null;
        }

        $path = sprintf('/api/v4/posts/%s', rawurlencode($event->postId()));
        $response = $this->requestWithChannelAccess($event, 'GET', $path, []);
        if (null === $response || $response['status_code'] >= 400) {
            $this->logFailedRequest('GET', $path, $response, [
                'channel_id' => $event->channelId(),
                'channel_type' => $event->channelType(),
                'operation' => 'resolve_thread_root',
            ]);

            return null;
        }

        try {
            $payload = json_decode($response['body'], true, flags: JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            $this->logger?->warning('Mattermost API returned an invalid source post payload while resolving thread root.', [
                'path' => $path,
                'response_body' => $this->truncateResponseBody($response['body']),
            ]);

            return null;
        }

        if (!is_array($payload)) {
            return null;
        }

        $channelId = $payload['channel_id'] ?? null;
        if (is_string($channelId) && '' !== $channelId && $channelId !== $event->channelId()) {
            $this->logger?->warning('Mattermost source post channel does not match the inbound event channel.', [
                'event_channel_id' => $event->channelId(),
                'source_post_channel_id' => $channelId,
                'source_post_id' => $event->postId(),
            ]);

            return null;
        }

        $rootId = $payload['root_id'] ?? null;
        if (is_string($rootId) && '' !== trim($rootId)) {
            return trim($rootId);
        }

        $postId = $payload['id'] ?? null;
        if (is_string($postId) && '' !== trim($postId)) {
            return trim($postId);
        }

        return null;
    }

    private function botUserId(): ?string
    {
        if (null !== $this->botUserId) {
            return $this->botUserId;
        }

        $response = $this->request('GET', '/api/v4/users/me');
        if (null === $response || $response['status_code'] >= 400) {
            $this->logFailedRequest('GET', '/api/v4/users/me', $response, [
                'operation' => 'load_bot_user',
            ]);

            return null;
        }

        try {
            $payload = json_decode($response['body'], true, flags: JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            $this->logger?->warning('Mattermost API returned an invalid bot user payload.', [
                'path' => '/api/v4/users/me',
                'response_body' => $this->truncateResponseBody($response['body']),
            ]);

            return null;
        }

        if (!is_array($payload) || !is_string($payload['id'] ?? null) || '' === trim($payload['id'])) {
            $this->logger?->warning('Mattermost API returned a bot user payload without an id.', [
                'path' => '/api/v4/users/me',
                'response_body' => $this->truncateResponseBody($response['body']),
            ]);

            return null;
        }

        $this->botUserId = trim($payload['id']);

        return $this->botUserId;
    }

    /**
     * @param array<string, string> $payload
     *
     * @return array{status_code: int, body: string}|null
     */
    private function request(string $method, string $path, array $payload = []): ?array
    {
        try {
            $options = [
                'auth_bearer' => $this->settings->botToken(),
                'headers' => ['Accept' => 'application/json'],
            ];
            if ([] !== $payload) {
                $options['json'] = $payload;
            }

            $response = $this->httpClient->request($method, $this->settings->baseUrl().$path, $options);

            return [
                'status_code' => $response->getStatusCode(),
                'body' => $response->getContent(false),
            ];
        } catch (TransportExceptionInterface) {
            $this->logger?->warning('Mattermost API request failed due to a transport error.', [
                'method' => $method,
                'path' => $path,
            ]);

            return null;
        }
    }

    /**
     * @param array{status_code: int, body: string}|null $response
     * @param array<string, mixed>                       $extraContext
     */
    private function logFailedRequest(string $method, string $path, ?array $response, array $extraContext = []): void
    {
        if (null === $response) {
            return;
        }

        $context = [
            'method' => $method,
            'path' => $path,
            'status_code' => $response['status_code'],
            ...$extraContext,
        ];

        $responseBody = trim($response['body']);
        if ('' !== $responseBody) {
            $context['response_body'] = $this->truncateResponseBody($responseBody);
        }

        $this->logger?->warning('Mattermost API request failed.', $context);
    }

    private function truncateResponseBody(string $body): string
    {
        if (strlen($body) <= 2000) {
            return $body;
        }

        return substr($body, 0, 2000).'...';
    }

    /**
     * @param array{status_code: int, body: string} $response
     */
    private function isInvalidRootIdResponse(array $response): bool
    {
        if (400 !== $response['status_code']) {
            return false;
        }

        return str_contains($response['body'], 'api.post.create_post.root_id.app_error')
            || str_contains($response['body'], 'Invalid RootId parameter');
    }

    private function replyRootId(MattermostInboundEvent $event): string
    {
        if ('' !== $event->rootId()) {
            return $event->rootId();
        }

        if (in_array($event->channelType(), ['D', 'G'], true)) {
            return '';
        }

        return $event->postId();
    }
}
