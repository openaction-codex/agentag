<?php

namespace App\AgentTag\Mattermost;

use App\AgentTag\Run\RunEventRecorder;
use App\Entity\AgentRun;
use Doctrine\ORM\EntityManagerInterface;

final readonly class MattermostRunProgressSinkFactory
{
    public function __construct(
        private MattermostNotifier $notifier,
        private TaskCardRenderer $renderer,
        private EntityManagerInterface $entityManager,
        private ?RunEventRecorder $runEventRecorder = null,
    ) {
    }

    public function create(AgentRun $run): MattermostRunProgressSink
    {
        return new MattermostRunProgressSink(
            $this->notifier,
            $this->eventFor($run),
            $run,
            $this->renderer,
            $this->entityManager,
            $this->runEventRecorder,
        );
    }

    public function eventFor(AgentRun $run): MattermostInboundEvent
    {
        $session = $run->session();
        $postId = $run->sourceEventId() ?? $session->threadId();
        $channelType = $session->threadId() === $session->channelId() ? 'D' : 'O';
        $rootId = 'D' === $channelType || $session->threadId() === $postId ? '' : $session->threadId();

        return new MattermostInboundEvent(
            $postId,
            '',
            $postId,
            $rootId,
            $session->channelId(),
            $channelType,
            $session->teamId(),
            $run->requesterId() ?? '',
            '',
        );
    }
}
