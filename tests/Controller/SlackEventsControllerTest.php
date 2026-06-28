<?php

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class SlackEventsControllerTest extends WebTestCase
{
    public function testItHandlesUrlVerification(): void
    {
        $client = static::createClient();

        $client->jsonRequest('POST', '/integrations/slack/events', [
            'type' => 'url_verification',
            'challenge' => 'challenge-token',
        ]);

        self::assertResponseIsSuccessful();
        self::assertStringContainsString('challenge-token', (string) $client->getResponse()->getContent());
    }

    public function testItIgnoresMessagesWithoutTheConfiguredTag(): void
    {
        $client = static::createClient();

        $client->jsonRequest('POST', '/integrations/slack/events', $this->payload(['text' => 'hello']));

        self::assertResponseIsSuccessful();
        self::assertStringContainsString('"handled":false', (string) $client->getResponse()->getContent());
    }

    public function testItAcceptsMentionedRootMessages(): void
    {
        $client = static::createClient();

        $client->jsonRequest('POST', '/integrations/slack/events', $this->payload(['text' => '@Codex help']));

        self::assertResponseIsSuccessful();
        self::assertStringContainsString('session `1700000000.000000`', (string) $client->getResponse()->getContent());
    }

    public function testItContinuesThreadRepliesUsingThreadTs(): void
    {
        $client = static::createClient();

        $client->jsonRequest('POST', '/integrations/slack/events', $this->payload([
            'text' => '@Codex continue',
            'thread_ts' => '1699999999.000000',
        ]));

        self::assertResponseIsSuccessful();
        self::assertStringContainsString('session `1699999999.000000`', (string) $client->getResponse()->getContent());
    }

    public function testItRejectsInvalidPayloads(): void
    {
        $client = static::createClient();

        $client->jsonRequest('POST', '/integrations/slack/events', ['type' => 'event_callback']);

        self::assertResponseStatusCodeSame(400);
    }

    /**
     * @param array<string, string> $eventOverrides
     *
     * @return array<string, mixed>
     */
    private function payload(array $eventOverrides = []): array
    {
        return [
            'type' => 'event_callback',
            'event_id' => 'Ev123',
            'team_id' => 'T123',
            'token' => '',
            'event' => array_replace([
                'type' => 'app_mention',
                'text' => '@Codex help',
                'ts' => '1700000000.000000',
                'thread_ts' => '',
                'channel' => 'C123',
                'user' => 'U123',
            ], $eventOverrides),
        ];
    }
}
