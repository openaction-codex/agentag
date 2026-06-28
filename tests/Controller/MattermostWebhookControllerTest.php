<?php

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class MattermostWebhookControllerTest extends WebTestCase
{
    public function testItIgnoresMessagesWithoutTheConfiguredTag(): void
    {
        $client = static::createClient();

        $client->jsonRequest('POST', '/integrations/mattermost/webhook', $this->payload(['text' => 'hello']));

        self::assertResponseStatusCodeSame(204);
    }

    public function testItAcceptsMentionedRootMessages(): void
    {
        $client = static::createClient();

        $client->jsonRequest('POST', '/integrations/mattermost/webhook', $this->payload(['text' => '@Codex help']));

        self::assertResponseIsSuccessful();
        self::assertResponseFormatSame('json');
        self::assertStringContainsString('session `post-id`', (string) $client->getResponse()->getContent());
    }

    public function testItContinuesThreadRepliesUsingTheRootId(): void
    {
        $client = static::createClient();

        $client->jsonRequest('POST', '/integrations/mattermost/webhook', $this->payload([
            'text' => '@Codex continue',
            'post_id' => 'reply-id',
            'root_id' => 'root-id',
        ]));

        self::assertResponseIsSuccessful();
        self::assertStringContainsString('session `root-id`', (string) $client->getResponse()->getContent());
    }

    public function testItRejectsInvalidPayloads(): void
    {
        $client = static::createClient();

        $client->jsonRequest('POST', '/integrations/mattermost/webhook', ['text' => '@Codex help']);

        self::assertResponseStatusCodeSame(400);
    }

    /**
     * @param array<string, string> $overrides
     *
     * @return array<string, string>
     */
    private function payload(array $overrides = []): array
    {
        return array_replace([
            'post_id' => 'post-id',
            'text' => '@Codex help',
            'root_id' => '',
            'channel_id' => 'channel-id',
            'channel_type' => 'O',
            'team_id' => 'team-id',
            'user_id' => 'user-id',
            'token' => '',
        ], $overrides);
    }
}
