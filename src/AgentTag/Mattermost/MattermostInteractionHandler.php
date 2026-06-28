<?php

namespace App\AgentTag\Mattermost;

final readonly class MattermostInteractionHandler
{
    public function __construct(
        private MattermostMentionDetector $mentionDetector,
        private MattermostSessionMapper $sessionMapper,
        private InboundEventIdempotencyStore $idempotencyStore,
        private MattermostNotifier $notifier,
    ) {
    }

    public function handle(MattermostInboundEvent $event): MattermostInteractionResult
    {
        if (!$this->mentionDetector->isMentioned($event->text())) {
            return MattermostInteractionResult::ignored();
        }

        if (!$this->idempotencyStore->remember($event->eventId())) {
            return MattermostInteractionResult::duplicate();
        }

        $session = $this->sessionMapper->map($event);
        $message = sprintf(
            'Accepted. I will continue this Mattermost thread as session `%s`.',
            $session->threadId(),
        );

        $this->notifier->showTyping($event);
        $this->notifier->postProgress($event, $message);

        return MattermostInteractionResult::handled($message);
    }
}
