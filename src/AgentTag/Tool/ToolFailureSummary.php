<?php

namespace App\AgentTag\Tool;

use App\AgentTag\Security\SensitiveTextRedactor;

final readonly class ToolFailureSummary
{
    public function __construct(private SensitiveTextRedactor $redactor)
    {
    }

    public function summarize(ToolDefinition $tool, string $errorOutput): string
    {
        return sprintf(
            'Tool `%s` failed: %s',
            $tool->name(),
            $this->redactor->redact($this->singleLine($errorOutput)),
        );
    }

    private function singleLine(string $value): string
    {
        return preg_replace('/\s+/', ' ', trim($value)) ?? trim($value);
    }
}
