<?php

namespace App\Tests\AgentTag\Chat;

use App\AgentTag\Chat\ConfiguredTagMentionDetector;
use App\AgentTag\Configuration\AgentTagSettings;
use PHPUnit\Framework\TestCase;

final class ConfiguredTagMentionDetectorTest extends TestCase
{
    public function testItDetectsTheConfiguredTagCaseInsensitively(): void
    {
        $detector = new ConfiguredTagMentionDetector($this->settings());

        self::assertTrue($detector->isMentioned('@Codex help me'));
        self::assertTrue($detector->isMentioned('hey @codex, draft this'));
    }

    public function testItDoesNotTriggerOnPartialWords(): void
    {
        $detector = new ConfiguredTagMentionDetector($this->settings());

        self::assertFalse($detector->isMentioned('@Codexical should not trigger'));
        self::assertFalse($detector->isMentioned('email me at user@Codex'));
    }

    private function settings(): AgentTagSettings
    {
        return new AgentTagSettings('@Codex', '/tmp/workspace');
    }
}
