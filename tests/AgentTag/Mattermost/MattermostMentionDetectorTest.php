<?php

namespace App\Tests\AgentTag\Mattermost;

use App\AgentTag\Configuration\AgentTagSettings;
use App\AgentTag\Mattermost\MattermostMentionDetector;
use PHPUnit\Framework\TestCase;

final class MattermostMentionDetectorTest extends TestCase
{
    public function testItDetectsTheConfiguredTagCaseInsensitively(): void
    {
        $detector = new MattermostMentionDetector($this->settings());

        self::assertTrue($detector->isMentioned('@Codex help me'));
        self::assertTrue($detector->isMentioned('hey @codex, draft this'));
    }

    public function testItDoesNotTriggerOnPartialWords(): void
    {
        $detector = new MattermostMentionDetector($this->settings());

        self::assertFalse($detector->isMentioned('@Codexical should not trigger'));
        self::assertFalse($detector->isMentioned('email me at user@Codex'));
    }

    private function settings(): AgentTagSettings
    {
        return new AgentTagSettings('@Codex', '/tmp/workspace', '/tmp/workspace/workflows', '');
    }
}
