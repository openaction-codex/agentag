<?php

namespace App\Tests\AgentTag\Security;

use App\AgentTag\Security\SensitiveTextRedactor;
use PHPUnit\Framework\TestCase;

final class SensitiveTextRedactorTest extends TestCase
{
    public function testItRedactsCommonSecretShapes(): void
    {
        $redactor = new SensitiveTextRedactor();

        $redacted = $redactor->redact(
            'password=hunter2 token: abc123 Authorization: Bearer abcdefghijklmnop ghp_abcdefghijklmnopqrstuvwxyz',
        );

        self::assertStringContainsString('password=[REDACTED]', $redacted);
        self::assertStringContainsString('token: [REDACTED]', $redacted);
        self::assertStringContainsString('Authorization: [REDACTED]', $redacted);
        self::assertStringContainsString('[REDACTED_GITHUB_TOKEN]', $redacted);
        self::assertStringNotContainsString('hunter2', $redacted);
        self::assertStringNotContainsString('abcdefghijklmnop', $redacted);
        self::assertStringNotContainsString('ghp_abcdefghijklmnopqrstuvwxyz', $redacted);
    }

    public function testItUsesConfiguredSensitivePatterns(): void
    {
        $redactor = new SensitiveTextRedactor('/project-[0-9]{4}/');

        self::assertSame('internal [REDACTED]', $redactor->redact('internal project-1234'));
    }

    public function testItAcceptsConfiguredPatternsAsJsonList(): void
    {
        $redactor = new SensitiveTextRedactor('["/ticket-[0-9]{1,3}/"]');

        self::assertSame('internal [REDACTED]', $redactor->redact('internal ticket-123'));
    }

    public function testItRejectsInvalidConfiguredPatterns(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid AgentTag redaction pattern');

        new SensitiveTextRedactor('/unterminated');
    }
}
