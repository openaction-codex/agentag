<?php

namespace App\Tests\AgentTag\Slack;

use App\AgentTag\Slack\SlackInboundEvent;
use App\AgentTag\Slack\SlackSettings;
use App\AgentTag\Slack\SlackWebhookAuthenticator;
use PHPUnit\Framework\TestCase;

final class SlackWebhookAuthenticatorTest extends TestCase
{
    public function testItAllowsAllPayloadsWhenNoTokenIsConfigured(): void
    {
        $authenticator = new SlackWebhookAuthenticator(new SlackSettings(true, ''));

        self::assertTrue($authenticator->isAllowed($this->event('anything')));
    }

    public function testItRequiresTheConfiguredToken(): void
    {
        $authenticator = new SlackWebhookAuthenticator(new SlackSettings(true, 'expected'));

        self::assertTrue($authenticator->isAllowed($this->event('expected')));
        self::assertFalse($authenticator->isAllowed($this->event('wrong')));
        self::assertTrue($authenticator->isUrlVerificationAllowed(['token' => 'expected']));
        self::assertFalse($authenticator->isUrlVerificationAllowed(['token' => 'wrong']));
    }

    private function event(string $token): SlackInboundEvent
    {
        return new SlackInboundEvent(
            'Ev123',
            '@Codex help',
            '1700000000.000000',
            '',
            'C123',
            'T123',
            'U123',
            $token,
        );
    }
}
