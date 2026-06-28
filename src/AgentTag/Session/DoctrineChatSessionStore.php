<?php

namespace App\AgentTag\Session;

use App\AgentTag\Chat\ChatSessionReference;
use App\AgentTag\Security\SensitiveTextRedactor;
use App\AgentTag\Workflow\WorkflowDefinition;
use App\Entity\AgentRun;
use App\Entity\ChatSession;
use Doctrine\ORM\EntityManagerInterface;

final readonly class DoctrineChatSessionStore implements ChatSessionStore
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private SessionContextSnapshotBuilder $snapshotBuilder,
        private SensitiveTextRedactor $redactor,
    ) {
    }

    #[\Override]
    public function recordRun(
        ChatSessionReference $reference,
        string $inputSummary,
        ChatThreadContext $threadContext,
        WorkflowDefinition $workflow,
    ): void {
        $now = new \DateTimeImmutable();
        $session = $this->entityManager->getRepository(ChatSession::class)->findOneBy([
            'sessionKey' => $reference->key(),
        ]);

        if (!$session instanceof ChatSession) {
            $session = new ChatSession(
                $reference->key(),
                $reference->platform(),
                $reference->teamId(),
                $reference->channelId(),
                $reference->threadId(),
                $now,
            );
            $this->entityManager->persist($session);
        }

        $session->touch($now);

        $priorRuns = $this->entityManager->getRepository(AgentRun::class)->findBy(
            ['session' => $session],
            ['id' => 'DESC'],
            5,
        );
        $contextSnapshot = $this->snapshotBuilder->build($session, $threadContext, $priorRuns, $workflow);

        $run = new AgentRun(
            $session,
            'accepted',
            $now,
            $this->redactor->redact($inputSummary),
            null,
            $this->redactor->redact($contextSnapshot),
            $workflow->name(),
            $workflow->version(),
            $workflow->revision(),
        );
        $this->entityManager->persist($run);
        $this->entityManager->flush();
    }
}
