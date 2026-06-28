<?php

namespace App\Entity;

use App\AgentTag\Approval\ActionSensitivity;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'approval_request')]
class ApprovalRequest
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_CANCELED = 'canceled';
    public const STATUS_EXPIRED = 'expired';
    public const STATUS_UNAUTHORIZED = 'unauthorized';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    public function __construct(
        #[ORM\Column(length: 120)]
        private string $action,
        #[ORM\Column(length: 80)]
        private string $targetSystem,
        #[ORM\Column(length: 120)]
        private string $workflowName,
        #[ORM\Column(length: 120)]
        private string $requesterId,
        #[ORM\Column(type: 'text')]
        private string $expectedEffect,
        #[ORM\Column(length: 32)]
        private string $sensitivity,
        #[ORM\Column]
        private \DateTimeImmutable $createdAt,
        #[ORM\Column(length: 32)]
        private string $status = self::STATUS_PENDING,
        #[ORM\Column(length: 120, nullable: true)]
        private ?string $approverId = null,
        #[ORM\Column(nullable: true)]
        private ?\DateTimeImmutable $decidedAt = null,
    ) {
        if (!in_array($sensitivity, [ActionSensitivity::SENSITIVE, ActionSensitivity::DESTRUCTIVE], true)) {
            throw new \InvalidArgumentException('Approval requests are only created for sensitive or destructive actions.');
        }
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function action(): string
    {
        return $this->action;
    }

    public function targetSystem(): string
    {
        return $this->targetSystem;
    }

    public function workflowName(): string
    {
        return $this->workflowName;
    }

    public function requesterId(): string
    {
        return $this->requesterId;
    }

    public function expectedEffect(): string
    {
        return $this->expectedEffect;
    }

    public function sensitivity(): string
    {
        return $this->sensitivity;
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function status(): string
    {
        return $this->status;
    }

    public function approverId(): ?string
    {
        return $this->approverId;
    }

    public function decidedAt(): ?\DateTimeImmutable
    {
        return $this->decidedAt;
    }

    public function approve(string $approverId, \DateTimeImmutable $now): string
    {
        if (!$this->isPending()) {
            return sprintf('Approval request is already %s.', $this->status);
        }

        $this->status = self::STATUS_APPROVED;
        $this->approverId = $approverId;
        $this->decidedAt = $now;

        return 'Action approved.';
    }

    public function cancel(string $approverId, \DateTimeImmutable $now): string
    {
        if (!$this->isPending()) {
            return sprintf('Approval request is already %s.', $this->status);
        }

        $this->status = self::STATUS_CANCELED;
        $this->approverId = $approverId;
        $this->decidedAt = $now;

        return 'Action canceled. Nothing was executed.';
    }

    public function expire(\DateTimeImmutable $now): string
    {
        if (!$this->isPending()) {
            return sprintf('Approval request is already %s.', $this->status);
        }

        $this->status = self::STATUS_EXPIRED;
        $this->decidedAt = $now;

        return 'Approval request expired. Nothing was executed.';
    }

    public function markUnauthorized(string $approverId, \DateTimeImmutable $now): string
    {
        if (!$this->isPending()) {
            return sprintf('Approval request is already %s.', $this->status);
        }

        $this->status = self::STATUS_UNAUTHORIZED;
        $this->approverId = $approverId;
        $this->decidedAt = $now;

        return 'You are not authorized to approve this action. Nothing was executed.';
    }

    public function chatPrompt(): string
    {
        return sprintf(
            "Confirmation required for `%s` on `%s`.\nWorkflow: `%s`\nRequester: `%s`\nExpected effect: %s",
            $this->action,
            $this->targetSystem,
            $this->workflowName,
            $this->requesterId,
            $this->expectedEffect,
        );
    }

    private function isPending(): bool
    {
        return self::STATUS_PENDING === $this->status;
    }
}
