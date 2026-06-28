<?php

namespace App\AgentTag\Tool;

final readonly class ToolPolicy
{
    public function requiresConfirmation(ToolDefinition $tool): bool
    {
        if (ToolDefinition::CONFIRMATION_ALWAYS === $tool->confirmationPolicy()) {
            return true;
        }

        return ToolDefinition::SENSITIVITY_NON_SENSITIVE !== $tool->sensitivity();
    }
}
