<?php

namespace App\Tests\AgentTag\Linear;

use App\AgentTag\Agent\AgentProfile;
use App\AgentTag\Approval\ApprovalRequestService;
use App\AgentTag\Configuration\AgentTagSettings;
use App\AgentTag\Linear\LinearOperationPolicy;
use App\AgentTag\Linear\LinearToolService;
use App\AgentTag\Linear\LinearToolUnavailableException;
use App\AgentTag\Security\SensitiveTextRedactor;
use App\AgentTag\Tool\ToolCatalog;
use App\AgentTag\Tool\ToolDefinition;
use App\Entity\ApprovalRequest;
use App\Entity\LinearWriteAudit;
use App\Tests\RefreshDatabaseTrait;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class LinearToolServiceTest extends KernelTestCase
{
    use RefreshDatabaseTrait;

    private string $workspaceDirectory;

    #[\Override]
    protected function setUp(): void
    {
        self::bootKernel();
        $this->refreshDatabase();

        $this->workspaceDirectory = sys_get_temp_dir().'/agentag-linear-workspace-'.bin2hex(random_bytes(6));
        mkdir($this->workspaceDirectory.'/tools', 0777, true);
        file_put_contents($this->workspaceDirectory.'/tools/linear.yaml', <<<'YAML'
name: linear
type: mcp
server: linear
sensitivity: non_sensitive
YAML);
    }

    #[\Override]
    protected function tearDown(): void
    {
        foreach (glob($this->workspaceDirectory.'/tools/*') ?: [] as $file) {
            unlink($file);
        }

        if (is_dir($this->workspaceDirectory.'/tools')) {
            rmdir($this->workspaceDirectory.'/tools');
        }

        if (is_dir($this->workspaceDirectory)) {
            rmdir($this->workspaceDirectory);
        }

        parent::tearDown();
    }

    public function testItPreparesLinearIssueReadsWhenWorkspaceConfiguresTheTool(): void
    {
        $instruction = $this->service()->readIssue($this->agent(), 'OPE-1114');

        self::assertSame(LinearOperationPolicy::READ_ISSUE, $instruction->operation());
        self::assertSame('OPE-1114', $instruction->targetIssueIdentifier());
        self::assertSame('linear', $instruction->tool()->name());
        self::assertSame(ToolDefinition::TYPE_MCP, $instruction->tool()->type());
        self::assertFalse($instruction->confirmationRequired());
        self::assertNull($instruction->approvalRequest());
        self::assertStringContainsString('MCP server `linear`', $instruction->runnerInstruction());
    }

    public function testItRejectsLinearRequestsWhenWorkspaceDoesNotConfigureTheTool(): void
    {
        foreach (glob($this->workspaceDirectory.'/tools/*') ?: [] as $file) {
            unlink($file);
        }

        $this->expectException(LinearToolUnavailableException::class);
        $this->expectExceptionMessage('Linear tool is not configured in the workspace.');

        $this->service()->readIssue($this->agent(), 'OPE-1114');
    }

    public function testItDoesNotCreateApprovalRequestsWhenLinearToolIsMissing(): void
    {
        foreach (glob($this->workspaceDirectory.'/tools/*') ?: [] as $file) {
            unlink($file);
        }

        try {
            $this->service()->prepareWrite(
                $this->agent(),
                LinearOperationPolicy::REPLACE_DESCRIPTION,
                'mattermost-post-1',
                'user-1',
                'OPE-1114',
                ['description' => 'Replacement text.'],
            );
            self::fail('Expected Linear tool lookup to fail.');
        } catch (LinearToolUnavailableException) {
            self::assertSame(0, $this->entityCount(ApprovalRequest::class));
        }
    }

    public function testItPreparesNonSensitiveWritesAndAuditsSuccess(): void
    {
        $instruction = $this->service()->prepareWrite(
            $this->agent(),
            LinearOperationPolicy::CREATE_COMMENT,
            'mattermost-post-1',
            'user-1',
            'OPE-1114',
            ['body' => 'Ready for review.'],
        );

        self::assertFalse($instruction->confirmationRequired());
        self::assertNull($instruction->approvalRequest());
        self::assertSame(0, $this->entityCount(ApprovalRequest::class));

        $audit = $this->service()->recordWriteSuccess(
            $this->agent(),
            LinearOperationPolicy::CREATE_COMMENT,
            'mattermost-post-1',
            'user-1',
            'OPE-1114',
            ['OPE-1114'],
        );

        self::assertSame(LinearWriteAudit::STATUS_SUCCEEDED, $audit->status());
        self::assertSame('mattermost-post-1', $audit->sourceMessageId());
        self::assertSame('agent', $audit->workflowName());
        self::assertSame('user-1', $audit->requesterId());
        self::assertSame('OPE-1114', $audit->targetIssueIdentifier());
        self::assertSame(['OPE-1114'], $audit->resultingIssueIdentifiers());
        self::assertSame(1, $this->entityCount(LinearWriteAudit::class));
    }

    public function testItRequestsConfirmationBeforeReplacingLinearContent(): void
    {
        $instruction = $this->service()->prepareWrite(
            $this->agent(),
            LinearOperationPolicy::REPLACE_DESCRIPTION,
            'mattermost-post-1',
            'user-1',
            'OPE-1114',
            ['description' => 'Replacement text.'],
        );

        self::assertTrue($instruction->confirmationRequired());
        $approvalRequest = $instruction->approvalRequest();
        self::assertInstanceOf(ApprovalRequest::class, $approvalRequest);
        self::assertSame(1, $this->entityCount(ApprovalRequest::class));
        self::assertStringContainsString('Confirmation required for `replace_description` on `linear`.', $approvalRequest->chatPrompt());
    }

    public function testItAuditsFailuresWithRedactedChatSummary(): void
    {
        $audit = $this->service()->recordWriteFailure(
            $this->agent(),
            LinearOperationPolicy::CREATE_COMMENT,
            'mattermost-post-1',
            'user-1',
            'OPE-1114',
            'token=linear-secret failed with 500',
        );

        self::assertSame(LinearWriteAudit::STATUS_FAILED, $audit->status());
        self::assertSame([], $audit->resultingIssueIdentifiers());
        self::assertSame('Linear `create_comment` failed: token=[REDACTED] failed with 500', $audit->failureSummary());
        self::assertSame(1, $this->entityCount(LinearWriteAudit::class));
    }

    private function service(): LinearToolService
    {
        return new LinearToolService(
            new ToolCatalog(new AgentTagSettings('@Codex', $this->workspaceDirectory, '')),
            new LinearOperationPolicy(),
            new ApprovalRequestService($this->entityManager()),
            $this->entityManager(),
            new SensitiveTextRedactor(),
        );
    }

    private function agent(): AgentProfile
    {
        return new AgentProfile('agent', $this->workspaceDirectory, null, 'codex-full-access', 1200);
    }

    /**
     * @param class-string $entityClass
     */
    private function entityCount(string $entityClass): int
    {
        return count($this->entityManager()->getRepository($entityClass)->findAll());
    }

    private function entityManager(): EntityManagerInterface
    {
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $entityManager);

        return $entityManager;
    }
}
