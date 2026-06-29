<?php

namespace App\Tests\AgentTag\Tool;

use App\AgentTag\Configuration\AgentTagSettings;
use App\AgentTag\Security\SensitiveTextRedactor;
use App\AgentTag\Tool\ToolCatalog;
use App\AgentTag\Tool\ToolDefinition;
use App\AgentTag\Tool\ToolFailureSummary;
use App\AgentTag\Tool\ToolPolicy;
use PHPUnit\Framework\TestCase;

final class ToolCatalogTest extends TestCase
{
    private string $workspaceDirectory;

    #[\Override]
    protected function setUp(): void
    {
        $this->workspaceDirectory = sys_get_temp_dir().'/agentag-tool-workspace-'.bin2hex(random_bytes(6));
        mkdir($this->workspaceDirectory.'/tools', 0777, true);

        file_put_contents($this->workspaceDirectory.'/tools/git.yaml', <<<'YAML'
name: git
type: cli
command: git
arguments:
    - status
working_directory: codebase
environment:
    - GIT_SSH_COMMAND
timeout_seconds: 120
sensitivity: non_sensitive
confirmation_policy: default
sandbox: no_sandbox
YAML);

        file_put_contents($this->workspaceDirectory.'/tools/deploy.yaml', <<<'YAML'
name: deploy
type: cli
command: ./deploy
working_directory: workspace
timeout_seconds: 600
sensitivity: destructive
sandbox: no_sandbox
YAML);
    }

    #[\Override]
    protected function tearDown(): void
    {
        foreach (glob($this->workspaceDirectory.'/tools/*') ?: [] as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }

        if (is_dir($this->workspaceDirectory.'/tools')) {
            rmdir($this->workspaceDirectory.'/tools');
        }

        if (is_dir($this->workspaceDirectory)) {
            rmdir($this->workspaceDirectory);
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
        self::assertSame('codebase', $toolByName['git']->workingDirectory());
        self::assertSame(['GIT_SSH_COMMAND'], $toolByName['git']->environmentWhitelist());
        self::assertSame(120, $toolByName['git']->timeoutSeconds());
        self::assertSame(ToolDefinition::SENSITIVITY_NON_SENSITIVE, $toolByName['git']->sensitivity());
        self::assertSame(ToolDefinition::SANDBOX_NO_SANDBOX, $toolByName['git']->sandbox());
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
        return new ToolCatalog(new AgentTagSettings('@Codex', $this->workspaceDirectory, ''));
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
