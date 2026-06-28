<?php

namespace App\AgentTag\Mattermost;

use App\AgentTag\Session\ChatThreadContext;
use App\AgentTag\Session\ChatThreadMessage;
use Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final readonly class MattermostApiThreadContextProvider implements MattermostThreadContextProvider
{
    public function __construct(
        private HttpClientInterface $httpClient,
        private MattermostApiSettings $settings,
    ) {
    }

    #[\Override]
    public function contextFor(MattermostInboundEvent $event): ChatThreadContext
    {
        if (!$this->settings->enabled()) {
            return $this->fallbackContext($event);
        }

        $rootPostId = '' !== $event->rootId() ? $event->rootId() : $event->postId();

        try {
            $response = $this->httpClient->request(
                'GET',
                sprintf('%s/api/v4/posts/%s/thread', $this->settings->baseUrl(), rawurlencode($rootPostId)),
                [
                    'auth_bearer' => $this->settings->botToken(),
                    'headers' => ['Accept' => 'application/json'],
                ],
            );

            if ($response->getStatusCode() >= 400) {
                return $this->fallbackContext($event);
            }

            $payload = $response->toArray(false);
        } catch (TransportExceptionInterface|DecodingExceptionInterface) {
            return $this->fallbackContext($event);
        }

        $payload = $this->stringKeyedPayload($payload);
        if (null === $payload) {
            return $this->fallbackContext($event);
        }

        return $this->contextFromPayload($payload, $rootPostId) ?? $this->fallbackContext($event);
    }

    private function fallbackContext(MattermostInboundEvent $event): ChatThreadContext
    {
        return ChatThreadContext::single($event->postId(), $event->userId(), $event->text());
    }

    /**
     * @param array<mixed, mixed> $payload
     *
     * @return array<string, mixed>|null
     */
    private function stringKeyedPayload(array $payload): ?array
    {
        $normalized = [];
        foreach ($payload as $key => $value) {
            if (!is_string($key)) {
                return null;
            }

            $normalized[$key] = $value;
        }

        return $normalized;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function contextFromPayload(array $payload, string $rootPostId): ?ChatThreadContext
    {
        $posts = $payload['posts'] ?? null;
        if (!is_array($posts)) {
            return null;
        }

        $order = $payload['order'] ?? array_keys($posts);
        if (!is_array($order)) {
            return null;
        }

        $messagesById = [];
        $orderedMessages = [];

        foreach ($order as $postId) {
            if (!is_string($postId)) {
                continue;
            }

            $post = $posts[$postId] ?? null;
            if (!is_array($post)) {
                continue;
            }

            $message = $this->messageFromPost($postId, $post);
            if (null === $message) {
                continue;
            }

            $messagesById[$message->externalId()] = $message;
            $orderedMessages[] = $message;
        }

        if ([] === $orderedMessages) {
            return null;
        }

        $rootMessage = $messagesById[$rootPostId] ?? null;
        $replyMessages = array_values(array_filter(
            $orderedMessages,
            static fn (ChatThreadMessage $message): bool => $message->externalId() !== $rootPostId,
        ));
        $replyLimit = null === $rootMessage ? $this->settings->recentReplyLimit() : $this->settings->recentReplyLimit() - 1;
        $recentReplies = array_slice($replyMessages, -max(0, $replyLimit));

        return new ChatThreadContext([
            ...null === $rootMessage ? [] : [$rootMessage],
            ...$recentReplies,
        ]);
    }

    /**
     * @param array<mixed, mixed> $post
     */
    private function messageFromPost(string $postId, array $post): ?ChatThreadMessage
    {
        $message = $post['message'] ?? null;
        if (!is_scalar($message)) {
            return null;
        }

        $userId = $post['user_id'] ?? '';
        if (!is_scalar($userId)) {
            $userId = '';
        }

        return new ChatThreadMessage($postId, trim((string) $userId), trim((string) $message));
    }
}
