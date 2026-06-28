<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'linear_write_audit')]
class LinearWriteAudit
{
    public const STATUS_SUCCEEDED = 'succeeded';
    public const STATUS_FAILED = 'failed';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /**
     * @param list<string> $resultingIssueIdentifiers
     */
    private function __construct(
        #[ORM\Column(length: 64)]
        private string $operation,
        #[ORM\Column(length: 160)]
        private string $sourceMessageId,
        #[ORM\Column(length: 120)]
        private string $workflowName,
        #[ORM\Column(length: 120)]
        private string $requesterId,
        #[ORM\Column(length: 80, nullable: true)]
        private ?string $targetIssueIdentifier,
        #[ORM\Column(type: 'json')]
        private array $resultingIssueIdentifiers,
        #[ORM\Column(length: 32)]
        private string $status,
        #[ORM\Column(type: 'text', nullable: true)]
        private ?string $failureSummary,
        #[ORM\Column]
        private \DateTimeImmutable $createdAt,
    ) {
    }

    /**
     * @param list<string> $resultingIssueIdentifiers
     */
    public static function succeeded(
        string $operation,
        string $sourceMessageId,
        string $workflowName,
        string $requesterId,
        ?string $targetIssueIdentifier,
        array $resultingIssueIdentifiers,
        \DateTimeImmutable $createdAt,
    ): self {
        if ([] === $resultingIssueIdentifiers) {
            throw new \InvalidArgumentException('Successful Linear write audits must include at least one resulting issue identifier.');
        }

        self::assertStringList($resultingIssueIdentifiers);

        return new self(
            $operation,
            $sourceMessageId,
            $workflowName,
            $requesterId,
            $targetIssueIdentifier,
            $resultingIssueIdentifiers,
            self::STATUS_SUCCEEDED,
            null,
            $createdAt,
        );
    }

    public static function failed(
        string $operation,
        string $sourceMessageId,
        string $workflowName,
        string $requesterId,
        ?string $targetIssueIdentifier,
        string $failureSummary,
        \DateTimeImmutable $createdAt,
    ): self {
        if ('' === trim($failureSummary)) {
            throw new \InvalidArgumentException('Failed Linear write audits must include a failure summary.');
        }

        return new self(
            $operation,
            $sourceMessageId,
            $workflowName,
            $requesterId,
            $targetIssueIdentifier,
            [],
            self::STATUS_FAILED,
            $failureSummary,
            $createdAt,
        );
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function operation(): string
    {
        return $this->operation;
    }

    public function sourceMessageId(): string
    {
        return $this->sourceMessageId;
    }

    public function workflowName(): string
    {
        return $this->workflowName;
    }

    public function requesterId(): string
    {
        return $this->requesterId;
    }

    public function targetIssueIdentifier(): ?string
    {
        return $this->targetIssueIdentifier;
    }

    /**
     * @return list<string>
     */
    public function resultingIssueIdentifiers(): array
    {
        return $this->resultingIssueIdentifiers;
    }

    public function status(): string
    {
        return $this->status;
    }

    public function failureSummary(): ?string
    {
        return $this->failureSummary;
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    /**
     * @param list<string> $values
     */
    private static function assertStringList(array $values): void
    {
        foreach ($values as $value) {
            if ('' === trim($value)) {
                throw new \InvalidArgumentException('Linear issue identifiers must not be blank.');
            }
        }
    }
}
