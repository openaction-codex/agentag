<?php

namespace App\Tests\AgentTag\Developer;

use App\AgentTag\Developer\DeveloperSpecInput;
use App\AgentTag\Developer\DeveloperSpecPromptBuilder;
use App\AgentTag\Workflow\WorkflowDefinition;
use PHPUnit\Framework\TestCase;

final class DeveloperSpecPromptBuilderTest extends TestCase
{
    public function testItBuildsDeveloperSpecPromptFromWorkflowTemplate(): void
    {
        $workflow = WorkflowDefinition::fromArray([
            'name' => 'developer',
            'version' => 'v1',
            'instructions' => 'Draft implementation-grade technical specs.',
            'output_template' => $this->template(),
        ], '/tmp/developer.yaml');

        $prompt = (new DeveloperSpecPromptBuilder())->build(
            $workflow,
            new DeveloperSpecInput('Functional spec text', 'OPE-123'),
        );

        self::assertStringContainsString('Workflow: developer', $prompt);
        self::assertStringContainsString('Draft implementation-grade technical specs.', $prompt);
        self::assertStringContainsString('## Data model', $prompt);
        self::assertStringContainsString('Functional spec input:', $prompt);
        self::assertStringContainsString('Functional spec text', $prompt);
        self::assertStringContainsString('Source Linear issue: OPE-123', $prompt);
        self::assertStringContainsString('link back to the source prompt or Linear issue', $prompt);
    }

    public function testItRejectsTemplatesMissingRequiredEngineeringSections(): void
    {
        $workflow = WorkflowDefinition::fromArray([
            'name' => 'developer',
            'instructions' => 'Draft specs.',
            'output_template' => "## Context\n## Tests",
        ], '/tmp/developer.yaml');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('must include "data model"');

        (new DeveloperSpecPromptBuilder())->build($workflow, new DeveloperSpecInput('Functional spec text'));
    }

    private function template(): string
    {
        return <<<'TEMPLATE'
## Context
## Data model
## Services
## APIs
## Execution flow
## Security
## Tests
## Migration/deployment
## Risks
## Rollout
TEMPLATE;
    }
}
