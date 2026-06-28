<?php

namespace App\Tests\AgentTag\Tool;

use App\AgentTag\Configuration\AgentTagSettings;
use App\AgentTag\Security\SensitiveTextRedactor;
use App\AgentTag\Tool\ToolCatalog;
use App\AgentTag\Tool\ToolDefinition;
use App\AgentTag\Tool\ToolFailureSummary;
use App\AgentTag\Tool\ToolPolicy;
use App\AgentTag\Workflow\WorkflowDefinition;
use PHPUnit\Framework\TestCase;

final class ToolCatalogTest extends TestCase
{
    private string $workflowDirectory;

    #[\Override]
    protected function setUp(): void
    {
        $this->workflowDirectory = sys_get_temp_dir().'/agentag-tool-workflows-'.bin2hex(random_bytes(6));
        mkdir($this->workflowDirectory.'/tools', 0777, true);

        file_put_contents($this->workflowDirectory.'/tools/git.yaml', <<<'YAML'
name: git
type: cli
command: git
arguments:
    - status
allowed_workflows:
    - developer
working_directory: codebase
environment:
    - GIT_SSH_COMMAND
timeout_seconds: 120
sensitivity: non_sensitive
confirmation_policy: default
sandbox: no_sandbox
YAML);

        file_put_contents($this->workflowDirectory.'/tools/deploy.yaml', <<<'YAML'
name: deploy
type: cli
command: ./deploy
allowed_workflows:
    - devops
working_directory: workspace
timeout_seconds: 600
sensitivity: destructive
sandbox: no_sandbox
YAML);
    }

    #[\Override]
    protected function tearDown(): void
    {
        foreach (glob($this->workflowDirectory.'/tools/*') ?: [] as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }

        if (is_dir($this->workflowDirectory.'/tools')) {
            rmdir($this->workflowDirectory.'/tools');
        }

        if (is_dir($this->workflowDirectory)) {
            rmdir($this->workflowDirectory);
        }
    }

    public function testItLoadsToolDefinitions(): void
    {
        $tools = $this->catalog()->all();
        $toolByName = $this->toolByName($tools);

        self::assertCount(2, $tools);
        self::assertArrayHasKey('git', $toolByName);
        self::assertSame('git', $toolByName['git']->name());
        self::assertSame(ToolDefinition::TYPE_CLI, $toolByName['git']->type());
        self::assertSame('git', $toolByName['git']->command());
        self::assertSame(['status'], $toolByName['git']->arguments());
        self::assertSame(['developer'], $toolByName['git']->allowedWorkflows());
        self::assertSame('codebase', $toolByName['git']->workingDirectory());
        self::assertSame(['GIT_SSH_COMMAND'], $toolByName['git']->environmentWhitelist());
        self::assertSame(120, $toolByName['git']->timeoutSeconds());
        self::assertSame(ToolDefinition::SENSITIVITY_NON_SENSITIVE, $toolByName['git']->sensitivity());
        self::assertSame(ToolDefinition::SANDBOX_NO_SANDBOX, $toolByName['git']->sandbox());
    }

    public function testItIncludesOnlyToolsPermittedForTheSelectedWorkflow(): void
    {
        $workflow = WorkflowDefinition::fromArray([
            'name' => 'developer',
            'tools' => ['git', 'deploy'],
        ], $this->workflowDirectory.'/developer.yaml');

        $tools = $this->catalog()->forWorkflow($workflow);

        self::assertCount(1, $tools);
        self::assertSame('git', $tools[0]->name());
    }

    public function testItRejectsUnknownWorkflowTools(): void
    {
        $workflow = WorkflowDefinition::fromArray([
            'name' => 'developer',
            'tools' => ['missing'],
        ], $this->workflowDirectory.'/developer.yaml');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown tool `missing` requested by workflow `developer`. Available tools: `codex`, `deploy`, `git`.');

        $this->catalog()->forWorkflow($workflow);
    }

    public function testItAllowsCodexAsBuiltInRunnerTool(): void
    {
        $workflow = WorkflowDefinition::fromArray([
            'name' => 'developer',
            'tools' => ['codex'],
        ], $this->workflowDirectory.'/developer.yaml');

        self::assertSame([], $this->catalog()->forWorkflow($workflow));
    }

    public function testPolicyRequiresConfirmationOnlyForSensitiveToolsByDefault(): void
    {
        $policy = new ToolPolicy();
        $toolByName = $this->toolByName($this->catalog()->all());

        self::assertFalse($policy->requiresConfirmation($toolByName['git']));
        self::assertTrue($policy->requiresConfirmation($toolByName['deploy']));
    }

    public function testFailureSummariesAreRedacted(): void
    {
        $summary = new ToolFailureSummary(new SensitiveTextRedactor());
        $tool = $this->toolByName($this->catalog()->all())['git'];

        $message = $summary->summarize($tool, 'failed with token=secret-value');

        self::assertSame('Tool `git` failed: failed with token=[REDACTED]', $message);
    }

    private function catalog(): ToolCatalog
    {
        return new ToolCatalog(new AgentTagSettings('@Codex', '/tmp/workspace', $this->workflowDirectory, ''));
    }

    /**
     * @param list<ToolDefinition> $tools
     *
     * @return array<string, ToolDefinition>
     */
    private function toolByName(array $tools): array
    {
        $toolByName = [];
        foreach ($tools as $tool) {
            $toolByName[$tool->name()] = $tool;
        }

        return $toolByName;
    }
}
