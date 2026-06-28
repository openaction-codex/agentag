<?php

namespace App\AgentTag\Linear;

use App\AgentTag\Approval\ActionSensitivity;

final readonly class LinearOperationPolicy
{
    public const READ_ISSUE = 'read_issue';
    public const CREATE_COMMENT = 'create_comment';
    public const CREATE_ISSUE = 'create_issue';
    public const CREATE_SUBISSUE = 'create_subissue';
    public const APPEND_DESCRIPTION = 'append_description';
    public const REPLACE_DESCRIPTION = 'replace_description';

    public function requiresConfirmation(string $operation): bool
    {
        return match ($operation) {
            self::READ_ISSUE,
            self::CREATE_COMMENT,
            self::CREATE_ISSUE,
            self::CREATE_SUBISSUE,
            self::APPEND_DESCRIPTION => false,
            self::REPLACE_DESCRIPTION => true,
            default => throw new \InvalidArgumentException(sprintf('Unknown Linear operation "%s".', $operation)),
        };
    }

    public function sensitivityFor(string $operation): string
    {
        return match ($operation) {
            self::READ_ISSUE,
            self::CREATE_COMMENT,
            self::CREATE_ISSUE,
            self::CREATE_SUBISSUE,
            self::APPEND_DESCRIPTION => ActionSensitivity::NON_SENSITIVE,
            self::REPLACE_DESCRIPTION => ActionSensitivity::SENSITIVE,
            default => throw new \InvalidArgumentException(sprintf('Unknown Linear operation "%s".', $operation)),
        };
    }
}
