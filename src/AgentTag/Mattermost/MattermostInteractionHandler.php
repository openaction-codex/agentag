<?php

namespace App\AgentTag\Mattermost;

use App\AgentTag\Chat\ConfiguredTagMentionDetector;
use App\AgentTag\Chat\InboundEventIdempotencyStore;
use App\AgentTag\Session\ChatSessionStore;

final readonly class MattermostInteractionHandler
{
    public function __construct(
        private ConfiguredTagMentionDetector $mentionDetector,
        private MattermostSessionMapper $sessionMapper,
        private InboundEventIdempotencyStore $idempotencyStore,
        private MattermostNotifier $notifier,
        private ChatSessionStore $sessionStore,
        private MattermostThreadContextProvider $threadContextProvider,
    ) {
    }

    public function handle(MattermostInboundEvent $event): MattermostInteractionResult
    {
        if (!$this->mentionDetector->isMentioned($event->text())) {
            return MattermostInteractionResult::ignored();
        }

        if (!$this->idempotencyStore->remember('mattermost:'.$event->eventId())) {
            return MattermostInteractionResult::duplicate();
        }

        $session = $this->sessionMapper->map($event);
        $this->sessionStore->recordRun(
            $session,
            sprintf('Mattermost message %s from user %s.', $event->eventId(), $event->userId()),
            $this->threadContextProvider->contextFor($event),
        );

        $message = sprintf(
            'Accepted. I will continue this Mattermost thread as session `%s`.',
            $session->threadId(),
        );

        $this->notifier->showTyping($event);
        $this->notifier->postProgress($event, $message);

        return MattermostInteractionResult::handled($message);
    }
}
