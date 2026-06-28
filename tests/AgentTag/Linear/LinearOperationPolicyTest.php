<?php

namespace App\Tests\AgentTag\Linear;

use App\AgentTag\Linear\LinearOperationPolicy;
use PHPUnit\Framework\TestCase;

final class LinearOperationPolicyTest extends TestCase
{
    public function testAppendOnlyLinearActionsAreNotSensitiveByDefault(): void
    {
        $policy = new LinearOperationPolicy();

        self::assertFalse($policy->requiresConfirmation(LinearOperationPolicy::READ_ISSUE));
        self::assertFalse($policy->requiresConfirmation(LinearOperationPolicy::CREATE_COMMENT));
        self::assertFalse($policy->requiresConfirmation(LinearOperationPolicy::CREATE_ISSUE));
        self::assertFalse($policy->requiresConfirmation(LinearOperationPolicy::CREATE_SUBISSUE));
        self::assertFalse($policy->requiresConfirmation(LinearOperationPolicy::APPEND_DESCRIPTION));
    }

    public function testOverwritingLinearIssueContentRequiresConfirmation(): void
    {
        self::assertTrue((new LinearOperationPolicy())->requiresConfirmation(LinearOperationPolicy::REPLACE_DESCRIPTION));
    }
}
