<?php

namespace App\AgentTag\Mattermost;

use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final readonly class MattermostApiNotifier implements MattermostNotifier
{
    public function __construct(
        private HttpClientInterface $httpClient,
        private MattermostApiSettings $settings,
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
            $this->httpClient->request($method, $this->settings->baseUrl().$path, [
                'auth_bearer' => $this->settings->botToken(),
                'json' => $payload,
                'headers' => ['Accept' => 'application/json'],
            ])->getStatusCode();
        } catch (TransportExceptionInterface) {
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
