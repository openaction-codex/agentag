<?php

namespace App\AgentTag\Linear;

final class LinearToolUnavailableException extends \RuntimeException
{
    public static function forWorkspace(): self
    {
        return new self('Linear tool is not configured in the workspace.');
    }
}
