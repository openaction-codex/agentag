<?php

namespace App\Tests\AgentTag\Workflow;

use App\AgentTag\Configuration\AgentTagSettings;
use App\AgentTag\Workflow\WorkflowCatalog;
use PHPUnit\Framework\TestCase;

final class WorkflowCatalogTest extends TestCase
{
    private string $workflowDirectory;

    #[\Override]
    protected function setUp(): void
    {
        $this->workflowDirectory = sys_get_temp_dir().'/agentag-workflows-'.bin2hex(random_bytes(6));
        mkdir($this->workflowDirectory);
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

    public function testItLoadsWorkflowYamlFiles(): void
    {
        file_put_contents($this->workflowDirectory.'/product.yaml', <<<'YAML'
name: product
description: Draft product specs.
triggers:
    - spec
tools:
    - linear
    - git
repositories:
    - openaction-codex-agentag
YAML);

        $catalog = new WorkflowCatalog($this->settings());
        $workflows = $catalog->all();

        self::assertCount(1, $workflows);
        self::assertSame('product', $workflows[0]->name());
        self::assertSame(['spec'], $workflows[0]->triggers());
        self::assertSame(['linear', 'git'], $workflows[0]->tools());
        self::assertSame(['openaction-codex-agentag'], $workflows[0]->repositories());
        self::assertSame(['git', 'linear'], $catalog->toolNames());
    }

    public function testItFailsWhenWorkflowDirectoryDoesNotExist(): void
    {
        $catalog = new WorkflowCatalog(new AgentTagSettings('@Codex', '/tmp/workspace', '/tmp/missing-workflows', ''));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('does not exist');

        $catalog->all();
    }

    private function settings(): AgentTagSettings
    {
        return new AgentTagSettings('@Codex', '/tmp/workspace', $this->workflowDirectory, '');
    }
}
