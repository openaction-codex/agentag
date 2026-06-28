<?php

namespace App\Tests\AgentTag\Mattermost;

use App\AgentTag\Mattermost\MattermostInboundEvent;
use App\AgentTag\Mattermost\MattermostWebhookAuthenticator;
use PHPUnit\Framework\TestCase;

final class MattermostWebhookAuthenticatorTest extends TestCase
{
    public function testItAllowsAllPayloadsWhenNoTokenIsConfigured(): void
    {
        $authenticator = new MattermostWebhookAuthenticator('');

        self::assertTrue($authenticator->isAllowed($this->event('anything')));
    }

    public function testItRequiresTheConfiguredToken(): void
    {
        $authenticator = new MattermostWebhookAuthenticator('expected');

        self::assertTrue($authenticator->isAllowed($this->event('expected')));
        self::assertFalse($authenticator->isAllowed($this->event('wrong')));
    }

    private function event(string $token): MattermostInboundEvent
    {
        return new MattermostInboundEvent(
            'post',
            '@Codex help',
            'post',
            '',
            'channel',
            'O',
            'team',
            'user',
            $token,
        );
    }
}
