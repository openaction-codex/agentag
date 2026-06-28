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
version: v2
description: Draft product specs.
default: true
triggers:
    - spec
allowed_tools:
    - linear
    - git
repositories:
    - openaction-codex-agentag
instructions: |
    Draft concise specs.
output_template: |
    ## Summary
runner_mode: codex-full-access
timeout_seconds: 900
sensitivity_policy: confirm-sensitive
YAML);

        $catalog = new WorkflowCatalog($this->settings());
        $workflows = $catalog->all();

        self::assertCount(1, $workflows);
        self::assertSame('product', $workflows[0]->name());
        self::assertSame('v2', $workflows[0]->version());
        self::assertTrue($workflows[0]->default());
        self::assertSame(['spec'], $workflows[0]->triggers());
        self::assertSame(['linear', 'git'], $workflows[0]->tools());
        self::assertSame(['openaction-codex-agentag'], $workflows[0]->repositories());
        self::assertSame('Draft concise specs.', $workflows[0]->instructions());
        self::assertSame('## Summary', $workflows[0]->outputTemplate());
        self::assertSame('codex-full-access', $workflows[0]->runnerMode());
        self::assertSame(900, $workflows[0]->timeoutSeconds());
        self::assertSame('confirm-sensitive', $workflows[0]->sensitivityPolicy());
        self::assertNull($workflows[0]->revision());
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
