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
}
