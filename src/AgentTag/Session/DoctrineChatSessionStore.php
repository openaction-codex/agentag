<?php

namespace App\AgentTag\Session;

use App\AgentTag\Agent\AgentProfile;
use App\AgentTag\Chat\ChatSessionReference;
use App\AgentTag\Security\SensitiveTextRedactor;
use App\AgentTag\Workspace\WorkspaceLayout;
use App\AgentTag\Workspace\WorkspaceTemplateCopier;
use App\Entity\AgentRun;
use App\Entity\ChatSession;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

final readonly class DoctrineChatSessionStore implements ChatSessionStore
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private SessionContextSnapshotBuilder $snapshotBuilder,
        private SensitiveTextRedactor $redactor,
        private WorkspaceLayout $workspaceLayout,
        private WorkspaceTemplateCopier $workspaceTemplateCopier,
        private LoggerInterface $logger,
    ) {
    }

    #[\Override]
    public function recordRun(
        ChatSessionReference $reference,
        string $inputSummary,
        ChatThreadContext $threadContext,
        AgentProfile $agent,
        ?string $sourceEventId = null,
        ?string $requesterId = null,
    ): AgentRun {
        $now = new \DateTimeImmutable();
        $session = $this->entityManager->getRepository(ChatSession::class)->findOneBy([
            'sessionKey' => $reference->key(),
        ]);

        if (!$session instanceof ChatSession) {
            $session = new ChatSession(
                $reference->key(),
                $reference->teamId(),
                $reference->channelId(),
                $reference->threadId(),
                $now,
            );
            $this->entityManager->persist($session);
            $this->logger->info('Created chat session.', [
                'session_key' => $reference->key(),
                'team_id' => $reference->teamId(),
                'channel_id' => $reference->channelId(),
                'thread_id' => $reference->threadId(),
            ]);
        }

        $session->touch($now);
        $workspacePath = $session->workspacePath() ?? $this->workspaceLayout->sessionPath($reference->key());
        $this->workspaceTemplateCopier->copy($agent->workspacePath(), $workspacePath);
        $session->assignWorkspacePath($workspacePath);

        $priorRuns = $this->entityManager->getRepository(AgentRun::class)->findBy(
            ['session' => $session],
            ['id' => 'DESC'],
            5,
        );
        $contextSnapshot = $this->snapshotBuilder->build(
            $session,
            $threadContext,
            $priorRuns,
            $agent,
        );

        $run = new AgentRun(
            $session,
            AgentRun::STATUS_ACCEPTED,
            $now,
            $this->redactor->redact($inputSummary),
            null,
            $this->redactor->redact($contextSnapshot),
            $agent->name(),
            null,
            $agent->workspaceRevision(),
            null === $sourceEventId ? null : $this->redactor->redact($sourceEventId),
            null === $requesterId ? null : $this->redactor->redact($requesterId),
            $this->redactor->redact($workspacePath),
        );
        $this->entityManager->persist($run);
        $this->entityManager->flush();

        $this->logger->info('Recorded agent run.', [
            'run_id' => $run->id(),
            'session_key' => $reference->key(),
            'agent' => $agent->name(),
            'workspace_path' => $workspacePath,
            'source_event_id' => $sourceEventId,
            'requester_id' => $requesterId,
        ]);

        return $run;
    }

    #[\Override]
    public function save(AgentRun $run): void
    {
        $this->entityManager->persist($run);
        $this->entityManager->flush();
    }
}
