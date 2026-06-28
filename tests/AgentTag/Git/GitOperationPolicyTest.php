<?php

namespace App\Tests\AgentTag\Git;

use App\AgentTag\Git\GitOperationPolicy;
use PHPUnit\Framework\TestCase;

final class GitOperationPolicyTest extends TestCase
{
    public function testOpeningPullRequestsIsNotSensitiveByDefault(): void
    {
        $policy = new GitOperationPolicy();

        self::assertFalse($policy->requiresConfirmation(GitOperationPolicy::OPEN_PULL_REQUEST));
        self::assertFalse($policy->requiresConfirmation(GitOperationPolicy::PUSH_BRANCH));
    }

    public function testDestructiveOrProtectedOperationsRequireConfirmation(): void
    {
        $policy = new GitOperationPolicy();

        self::assertTrue($policy->requiresConfirmation(GitOperationPolicy::PUSH_MAIN));
        self::assertTrue($policy->requiresConfirmation(GitOperationPolicy::FORCE_PUSH));
        self::assertTrue($policy->requiresConfirmation(GitOperationPolicy::DELETE_DATA));
        self::assertTrue($policy->requiresConfirmation(GitOperationPolicy::OVERWRITE_DATA));
        self::assertTrue($policy->requiresConfirmation(GitOperationPolicy::DEPLOY));
    }
}
