<?php

namespace App\AgentTag\Approval;

use App\Entity\ApprovalRequest;
use Doctrine\ORM\EntityManagerInterface;

final readonly class ApprovalRequestService
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function requestIfRequired(
        string $sensitivity,
        string $action,
        string $targetSystem,
        string $workflowName,
        string $requesterId,
        string $expectedEffect,
    ): ?ApprovalRequest {
        if (ActionSensitivity::NON_SENSITIVE === $sensitivity) {
            return null;
        }

        $request = new ApprovalRequest($action, $targetSystem, $workflowName, $requesterId, $expectedEffect, $sensitivity, new \DateTimeImmutable());
        $this->entityManager->persist($request);
        $this->entityManager->flush();

        return $request;
    }

    public function approve(ApprovalRequest $request, string $approverId): string
    {
        $message = '' === trim($approverId)
            ? $request->markUnauthorized($approverId, new \DateTimeImmutable())
            : $request->approve($approverId, new \DateTimeImmutable());
        $this->entityManager->flush();

        return $message;
    }

    public function cancel(ApprovalRequest $request, string $approverId): string
    {
        $message = $request->cancel($approverId, new \DateTimeImmutable());
        $this->entityManager->flush();

        return $message;
    }

    public function expire(ApprovalRequest $request): string
    {
        $message = $request->expire(new \DateTimeImmutable());
        $this->entityManager->flush();

        return $message;
    }
}
