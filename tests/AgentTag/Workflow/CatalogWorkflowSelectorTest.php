<?php

namespace App\Tests\AgentTag\Workflow;

use App\AgentTag\Configuration\AgentTagSettings;
use App\AgentTag\Workflow\CatalogWorkflowSelector;
use App\AgentTag\Workflow\WorkflowCatalog;
use PHPUnit\Framework\TestCase;

final class CatalogWorkflowSelectorTest extends TestCase
{
    private string $workflowDirectory;

    #[\Override]
    protected function setUp(): void
    {
        $this->workflowDirectory = sys_get_temp_dir().'/agentag-selector-workflows-'.bin2hex(random_bytes(6));
        mkdir($this->workflowDirectory);

        file_put_contents($this->workflowDirectory.'/developer.yaml', <<<'YAML'
name: developer
version: v1
default: true
triggers:
    - implement
    - fix
tools:
    - codex
YAML);

        file_put_contents($this->workflowDirectory.'/product.yaml', <<<'YAML'
name: product
version: v2
triggers:
    - spec
    - roadmap
tools:
    - linear
YAML);
    }

    #[\Override]
    protected function tearDown(): void
    {
        foreach (glob($this->workflowDirectory.'/*') ?: [] as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }

        if (is_dir($this->workflowDirectory)) {
            rmdir($this->workflowDirectory);
        }
    }

    public function testItSelectsExplicitWorkflowCommands(): void
    {
        $selection = $this->selector()->select('@Codex workflow:product draft a spec');

        self::assertTrue($selection->isSelected());
        self::assertSame('product', $selection->workflow()->name());
    }

    public function testItSelectsSlashWorkflowCommands(): void
    {
        $selection = $this->selector()->select('@Codex /product draft a spec');

        self::assertTrue($selection->isSelected());
        self::assertSame('product', $selection->workflow()->name());
    }

    public function testItSelectsByIntentTrigger(): void
    {
        $selection = $this->selector()->select('@Codex please implement this');

        self::assertTrue($selection->isSelected());
        self::assertSame('developer', $selection->workflow()->name());
    }

    public function testItUsesConfiguredDefaultWhenNoIntentMatches(): void
    {
        $selection = $this->selector()->select('@Codex hello');

        self::assertTrue($selection->isSelected());
        self::assertSame('developer', $selection->workflow()->name());
    }

    public function testItReturnsAvailableOptionsForUnknownExplicitWorkflow(): void
    {
        $selection = $this->selector()->select('@Codex workflow:sales follow up');

        self::assertFalse($selection->isSelected());
        self::assertSame('Unknown workflow `sales`. Available workflows: `developer`, `product`.', $selection->message());
    }

    private function selector(): CatalogWorkflowSelector
    {
        $settings = new AgentTagSettings('@Codex', '/tmp/workspace', $this->workflowDirectory, '');

        return new CatalogWorkflowSelector($settings, new WorkflowCatalog($settings));
    }
}
