<?php

namespace App\Tests\AgentTag\Approval;

use App\AgentTag\Approval\ActionSensitivity;
use App\AgentTag\Approval\ApprovalRequestService;
use App\Entity\AgentRun;
use App\Entity\ApprovalRequest;
use App\Entity\ChatSession;
use App\Tests\RefreshDatabaseTrait;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class ApprovalRequestServiceTest extends KernelTestCase
{
    use RefreshDatabaseTrait;

    #[\Override]
    protected function setUp(): void
    {
        self::bootKernel();
        $this->refreshDatabase();
    }

    public function testItDoesNotCreateRequestsForNonSensitiveActions(): void
    {
        $request = $this->service()->requestIfRequired(
            ActionSensitivity::NON_SENSITIVE,
            'open_pull_request',
            'github',
            'developer',
            'user-1',
            'Open a review PR.',
        );

        self::assertNull($request);
        self::assertSame(0, $this->approvalCount());
    }

    public function testItCreatesAndApprovesSensitiveRequests(): void
    {
        $request = $this->service()->requestIfRequired(
            ActionSensitivity::SENSITIVE,
            'push_main',
            'github',
            'developer',
            'user-1',
            'Push changes to main.',
        );

        self::assertInstanceOf(ApprovalRequest::class, $request);
        self::assertStringContainsString('Confirmation required for `push_main` on `github`.', $request->chatPrompt());
        self::assertStringContainsString('Workflow: `developer`', $request->chatPrompt());
        self::assertSame('Action approved.', $this->service()->approve($request, 'reviewer-1'));
        self::assertSame(ApprovalRequest::STATUS_APPROVED, $request->status());
        self::assertSame('reviewer-1', $request->approverId());
        self::assertNotNull($request->decidedAt());
    }

    public function testItCanLinkRequestsToTheCreatingRun(): void
    {
        $run = $this->persistRun();

        $request = $this->service()->requestIfRequired(
            ActionSensitivity::SENSITIVE,
            'push_main',
            'github',
            'developer',
            'user-1',
            'Push changes to main.',
            $run,
        );

        self::assertInstanceOf(ApprovalRequest::class, $request);
        self::assertSame($run, $request->run());
    }

    public function testItCancelsAndExpiresWithoutExecuting(): void
    {
        $request = $this->sensitiveRequest('deploy');

        self::assertSame('Action canceled. Nothing was executed.', $this->service()->cancel($request, 'reviewer-1'));
        self::assertSame(ApprovalRequest::STATUS_CANCELED, $request->status());
        self::assertSame('Approval request is already canceled.', $this->service()->expire($request));
    }

    public function testItMarksBlankApproverAsUnauthorized(): void
    {
        $request = $this->sensitiveRequest('force_push');

        self::assertSame('You are not authorized to approve this action. Nothing was executed.', $this->service()->approve($request, ''));
        self::assertSame(ApprovalRequest::STATUS_UNAUTHORIZED, $request->status());
    }

    private function sensitiveRequest(string $action): ApprovalRequest
    {
        $request = $this->service()->requestIfRequired(
            ActionSensitivity::DESTRUCTIVE,
            $action,
            'github',
            'developer',
            'user-1',
            'High-risk external effect.',
        );
        self::assertInstanceOf(ApprovalRequest::class, $request);

        return $request;
    }

    private function service(): ApprovalRequestService
    {
        return new ApprovalRequestService($this->entityManager());
    }

    private function approvalCount(): int
    {
        return count($this->entityManager()->getRepository(ApprovalRequest::class)->findAll());
    }

    private function entityManager(): EntityManagerInterface
    {
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $entityManager);

        return $entityManager;
    }

    private function persistRun(): AgentRun
    {
        $session = new ChatSession('mattermost:team:channel:thread', 'mattermost', 'team', 'channel', 'thread', new \DateTimeImmutable());
        $run = new AgentRun($session, 'accepted', new \DateTimeImmutable());
        $this->entityManager()->persist($session);
        $this->entityManager()->persist($run);
        $this->entityManager()->flush();

        return $run;
    }
}
