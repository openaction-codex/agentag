<?php

namespace App\Tests\AgentTag\Configuration;

use App\AgentTag\Configuration\AgentTagSettings;
use PHPUnit\Framework\TestCase;

final class AgentTagSettingsTest extends TestCase
{
    public function testItAcceptsTheDefaultCodexTag(): void
    {
        $settings = new AgentTagSettings(
            '@Codex',
            '/srv/agentag/workspace',
            '',
        );

        self::assertSame('@Codex', $settings->tag());
        self::assertSame('/srv/agentag/workspace', $settings->workspacePath());
        self::assertSame(1200, $settings->runTimeoutSeconds());
        self::assertCount(0, $settings->repositories());
    }

    public function testItRejectsInvalidTags(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('AgentTag tag must look like @Codex');

        new AgentTagSettings(
            'Codex',
            '/srv/agentag/workspace',
            '',
        );
    }
}
