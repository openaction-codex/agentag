<?php

namespace App\AgentTag\Slack;

use Symfony\Component\HttpFoundation\Request;

final readonly class SlackPayloadParser
{
    /**
     * @return array<string, mixed>
     */
    public function payload(Request $request): array
    {
        $payload = json_decode($request->getContent(), true);
        if (!is_array($payload)) {
            throw new \InvalidArgumentException('Slack event payload must be a JSON object.');
        }

        return $this->stringKeyedPayload($payload);
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function parseEvent(array $payload): SlackInboundEvent
    {
        $event = $payload['event'] ?? null;
        if (!is_array($event)) {
            throw new \InvalidArgumentException('Slack event payload is missing "event".');
        }

        $event = $this->stringKeyedPayload($event);

        return new SlackInboundEvent(
            $this->requiredString($payload, 'event_id'),
            $this->optionalString($event, 'text'),
            $this->requiredString($event, 'ts'),
            $this->optionalString($event, 'thread_ts'),
            $this->requiredString($event, 'channel'),
            $this->optionalString($payload, 'team_id'),
            $this->optionalString($event, 'user'),
            $this->optionalString($payload, 'token'),
        );
    }

    /**
     * @param array<mixed, mixed> $payload
     *
     * @return array<string, mixed>
     */
    private function stringKeyedPayload(array $payload): array
    {
        $normalized = [];
        foreach ($payload as $key => $value) {
            if (!is_string($key)) {
                throw new \InvalidArgumentException('Slack event payload must use string keys.');
            }

            $normalized[$key] = $value;
        }

        return $normalized;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function requiredString(array $payload, string $key): string
    {
        $value = $this->optionalString($payload, $key);
        if ('' === $value) {
            throw new \InvalidArgumentException(sprintf('Slack event payload is missing "%s".', $key));
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function optionalString(array $payload, string $key): string
    {
        $value = $payload[$key] ?? '';
        if (!is_scalar($value)) {
            throw new \InvalidArgumentException(sprintf('Slack event field "%s" must be scalar.', $key));
        }

        return trim((string) $value);
    }
}
