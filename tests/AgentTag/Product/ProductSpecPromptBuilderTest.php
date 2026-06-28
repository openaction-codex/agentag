<?php

namespace App\Tests\AgentTag\Product;

use App\AgentTag\Codebase\CodebaseContext;
use App\AgentTag\Codebase\RepositoryClone;
use App\AgentTag\Configuration\ConfiguredRepository;
use App\AgentTag\Product\ProductSpecInput;
use App\AgentTag\Product\ProductSpecPromptBuilder;
use App\AgentTag\Session\ChatThreadContext;
use App\AgentTag\Session\ChatThreadMessage;
use App\AgentTag\Workflow\WorkflowDefinition;
use PHPUnit\Framework\TestCase;

final class ProductSpecPromptBuilderTest extends TestCase
{
    public function testItBuildsProductSpecPromptFromWorkflowTemplateAndInputs(): void
    {
        $workflow = WorkflowDefinition::fromArray([
            'name' => 'product',
            'version' => 'v1',
            'instructions' => 'Draft complete product specs.',
            'output_template' => "## Problem\n## User stories\n## Open questions",
        ], '/tmp/product.yaml');
        $input = new ProductSpecInput(
            'Build team notifications.',
            new ChatThreadContext([
                new ChatThreadMessage('root', 'alice', 'Need notifications'),
            ]),
            new CodebaseContext([
                new RepositoryClone(ConfiguredRepository::fromSshUrl('git@github.com:openaction-codex/agentag.git'), '/tmp/run/codebase/openaction-codex-agentag'),
            ]),
            'OPE-123',
        );

        $prompt = (new ProductSpecPromptBuilder())->build($workflow, $input);

        self::assertStringContainsString('Workflow: product', $prompt);
        self::assertStringContainsString('Draft complete product specs.', $prompt);
        self::assertStringContainsString('## User stories', $prompt);
        self::assertStringContainsString('Inline request:', $prompt);
        self::assertStringContainsString('Need notifications', $prompt);
        self::assertStringContainsString('Repository context:', $prompt);
        self::assertStringContainsString('Linear issue input: OPE-123', $prompt);
        self::assertStringContainsString('Open questions section', $prompt);
    }

    public function testItRequiresExternalWorkflowInstructionsAndTemplate(): void
    {
        $workflow = WorkflowDefinition::fromArray(['name' => 'product'], '/tmp/product.yaml');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('must define instructions and output_template');

        (new ProductSpecPromptBuilder())->build($workflow, new ProductSpecInput('Draft this.'));
    }
}
