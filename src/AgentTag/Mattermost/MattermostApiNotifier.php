<?php

namespace App\AgentTag\Mattermost;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final readonly class MattermostApiNotifier implements MattermostNotifier
{
    public function __construct(
        private HttpClientInterface $httpClient,
        private MattermostApiSettings $settings,
        private ?LoggerInterface $logger = null,
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

        $this->request('POST', '/api/v4/users/me/typing', $payload);
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

        $this->request('POST', '/api/v4/posts', $payload);
    }

    /**
     * @param array<string, string> $payload
     */
    private function request(string $method, string $path, array $payload): void
    {
        try {
            $statusCode = $this->httpClient->request($method, $this->settings->baseUrl().$path, [
                'auth_bearer' => $this->settings->botToken(),
                'json' => $payload,
                'headers' => ['Accept' => 'application/json'],
            ])->getStatusCode();

            if ($statusCode >= 400) {
                $this->logger?->warning('Mattermost API request failed.', [
                    'method' => $method,
                    'path' => $path,
                    'status_code' => $statusCode,
                ]);
            }
        } catch (TransportExceptionInterface) {
            $this->logger?->warning('Mattermost API request failed due to a transport error.', [
                'method' => $method,
                'path' => $path,
            ]);
        }
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
