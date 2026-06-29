<?php

namespace App\AgentTag\Mattermost;

use App\AgentTag\Run\RunEventRecorder;
use App\Entity\AgentRun;

final readonly class MattermostRunProgressSinkFactory
{
    public function __construct(
        private MattermostNotifier $notifier,
        private ?RunEventRecorder $runEventRecorder = null,
    ) {
    }

    public function create(AgentRun $run): MattermostRunProgressSink
    {
        $session = $run->session();
        $postId = $run->sourceEventId() ?? $session->threadId();
        $channelType = $session->threadId() === $session->channelId() ? 'D' : 'O';
        $rootId = 'D' === $channelType || $session->threadId() === $postId ? '' : $session->threadId();

        return new MattermostRunProgressSink(
            $this->notifier,
            new MattermostInboundEvent(
                $postId,
                '',
                $postId,
                $rootId,
                $session->channelId(),
                $channelType,
                $session->teamId(),
                $run->requesterId() ?? '',
                '',
            ),
            $run,
            $this->runEventRecorder,
        );
    }
}
