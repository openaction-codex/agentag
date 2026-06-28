<?php

namespace App\AgentTag\Linear;

use App\AgentTag\Approval\ApprovalRequestService;
use App\AgentTag\Security\SensitiveTextRedactor;
use App\AgentTag\Tool\ToolCatalog;
use App\AgentTag\Tool\ToolDefinition;
use App\AgentTag\Workflow\WorkflowDefinition;
use App\Entity\ApprovalRequest;
use App\Entity\LinearWriteAudit;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

final readonly class LinearToolService
{
    /**
     * @var list<string>
     */
    private const WRITE_OPERATIONS = [
        LinearOperationPolicy::CREATE_COMMENT,
        LinearOperationPolicy::CREATE_ISSUE,
        LinearOperationPolicy::CREATE_SUBISSUE,
        LinearOperationPolicy::APPEND_DESCRIPTION,
        LinearOperationPolicy::REPLACE_DESCRIPTION,
    ];

    public function __construct(
        private ToolCatalog $toolCatalog,
        private LinearOperationPolicy $operationPolicy,
        private ApprovalRequestService $approvalRequestService,
        private EntityManagerInterface $entityManager,
        private SensitiveTextRedactor $redactor,
        private ?LoggerInterface $logger = null,
    ) {
    }

    public function readIssue(WorkflowDefinition $workflow, string $issueIdentifier): LinearToolInstruction
    {
        $issueIdentifier = $this->requiredString($issueIdentifier, 'issue identifier');
        $tool = $this->linearToolFor($workflow);
        $this->logger?->info('Prepared Linear issue read.', [
            'workflow' => $workflow->name(),
            'tool' => $tool->name(),
            'issue_identifier' => $issueIdentifier,
        ]);

        return new LinearToolInstruction(
            $tool,
            LinearOperationPolicy::READ_ISSUE,
            $issueIdentifier,
            ['issue_identifier' => $issueIdentifier],
            false,
            null,
        );
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function prepareWrite(
        WorkflowDefinition $workflow,
        string $operation,
        string $sourceMessageId,
        string $requesterId,
        ?string $targetIssueIdentifier,
        array $payload,
    ): LinearToolInstruction {
        $this->assertWriteOperation($operation);
        $tool = $this->linearToolFor($workflow);
        $targetIssueIdentifier = $this->optionalString($targetIssueIdentifier);
        $requesterId = $this->requiredString($requesterId, 'requester id');
        $sourceMessageId = $this->requiredString($sourceMessageId, 'source message id');

        $approvalRequest = $this->approvalRequestService->requestIfRequired(
            $this->operationPolicy->sensitivityFor($operation),
            $operation,
            'linear',
            $workflow->name(),
            $requesterId,
            $this->expectedEffect($operation, $targetIssueIdentifier, $sourceMessageId),
        );
        $this->logger?->info('Prepared Linear write.', [
            'workflow' => $workflow->name(),
            'tool' => $tool->name(),
            'operation' => $operation,
            'target_issue_identifier' => $targetIssueIdentifier,
            'source_message_id' => $sourceMessageId,
            'requester_id' => $requesterId,
            'confirmation_required' => $approvalRequest instanceof ApprovalRequest,
        ]);

        return new LinearToolInstruction(
            $tool,
            $operation,
            $targetIssueIdentifier,
            $payload,
            $approvalRequest instanceof ApprovalRequest,
            $approvalRequest,
        );
    }

    /**
     * @param list<string> $resultingIssueIdentifiers
     */
    public function recordWriteSuccess(
        WorkflowDefinition $workflow,
        string $operation,
        string $sourceMessageId,
        string $requesterId,
        ?string $targetIssueIdentifier,
        array $resultingIssueIdentifiers,
    ): LinearWriteAudit {
        $this->assertWriteOperation($operation);
        $this->linearToolFor($workflow);

        $audit = LinearWriteAudit::succeeded(
            $operation,
            $this->requiredString($sourceMessageId, 'source message id'),
            $workflow->name(),
            $this->requiredString($requesterId, 'requester id'),
            $this->optionalString($targetIssueIdentifier),
            $resultingIssueIdentifiers,
            new \DateTimeImmutable(),
        );
        $this->entityManager->persist($audit);
        $this->entityManager->flush();
        $this->logger?->info('Audited successful Linear write.', [
            'audit_id' => $audit->id(),
            'workflow' => $workflow->name(),
            'operation' => $operation,
            'resulting_issue_identifiers' => $resultingIssueIdentifiers,
        ]);

        return $audit;
    }

    public function recordWriteFailure(
        WorkflowDefinition $workflow,
        string $operation,
        string $sourceMessageId,
        string $requesterId,
        ?string $targetIssueIdentifier,
        string $errorOutput,
    ): LinearWriteAudit {
        $this->assertWriteOperation($operation);
        $this->linearToolFor($workflow);

        $audit = LinearWriteAudit::failed(
            $operation,
            $this->requiredString($sourceMessageId, 'source message id'),
            $workflow->name(),
            $this->requiredString($requesterId, 'requester id'),
            $this->optionalString($targetIssueIdentifier),
            $this->summarizeFailure($operation, $errorOutput),
            new \DateTimeImmutable(),
        );
        $this->entityManager->persist($audit);
        $this->entityManager->flush();
        $this->logger?->warning('Audited failed Linear write.', [
            'audit_id' => $audit->id(),
            'workflow' => $workflow->name(),
            'operation' => $operation,
            'target_issue_identifier' => $targetIssueIdentifier,
        ]);

        return $audit;
    }

    public function summarizeFailure(string $operation, string $errorOutput): string
    {
        $summary = preg_replace('/\s+/', ' ', trim($this->redactor->redact($errorOutput))) ?? trim($errorOutput);
        if ('' === $summary) {
            $summary = 'unknown Linear tool error';
        }

        return sprintf('Linear `%s` failed: %s', $operation, substr($summary, 0, 500));
    }

    private function linearToolFor(WorkflowDefinition $workflow): ToolDefinition
    {
        foreach ($this->toolCatalog->forWorkflow($workflow) as $tool) {
            if ('linear' === $tool->name()) {
                return $tool;
            }
        }

        throw LinearToolUnavailableException::forWorkflow($workflow);
    }

    private function assertWriteOperation(string $operation): void
    {
        if (!in_array($operation, self::WRITE_OPERATIONS, true)) {
            throw new \InvalidArgumentException(sprintf('Linear operation "%s" is not a write operation.', $operation));
        }
    }

    private function expectedEffect(string $operation, ?string $targetIssueIdentifier, string $sourceMessageId): string
    {
        $target = null === $targetIssueIdentifier ? 'a Linear issue' : sprintf('Linear issue %s', $targetIssueIdentifier);

        return sprintf('Run `%s` against %s from chat message %s.', $operation, $target, $sourceMessageId);
    }

    private function requiredString(string $value, string $field): string
    {
        $value = trim($value);
        if ('' === $value) {
            throw new \InvalidArgumentException(sprintf('Linear %s must not be blank.', $field));
        }

        return $value;
    }

    private function optionalString(?string $value): ?string
    {
        if (null === $value) {
            return null;
        }

        $value = trim($value);

        return '' === $value ? null : $value;
    }
}
