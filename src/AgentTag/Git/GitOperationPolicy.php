<?php

namespace App\AgentTag\Git;

final readonly class GitOperationPolicy
{
    public const OPEN_PULL_REQUEST = 'open_pull_request';
    public const PUSH_BRANCH = 'push_branch';
    public const PUSH_MAIN = 'push_main';
    public const FORCE_PUSH = 'force_push';
    public const DELETE_DATA = 'delete_data';
    public const OVERWRITE_DATA = 'overwrite_data';
    public const DEPLOY = 'deploy';

    public function requiresConfirmation(string $operation): bool
    {
        return match ($operation) {
            self::OPEN_PULL_REQUEST,
            self::PUSH_BRANCH => false,
            self::PUSH_MAIN,
            self::FORCE_PUSH,
            self::DELETE_DATA,
            self::OVERWRITE_DATA,
            self::DEPLOY => true,
            default => throw new \InvalidArgumentException(sprintf('Unknown git operation "%s".', $operation)),
        };
    }
}
