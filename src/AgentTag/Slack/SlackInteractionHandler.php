<?php

namespace App\AgentTag\Slack;

use App\AgentTag\Chat\ConfiguredTagMentionDetector;
use App\AgentTag\Chat\InboundEventIdempotencyStore;

final readonly class SlackInteractionHandler
{
    public function __construct(
        private ConfiguredTagMentionDetector $mentionDetector,
        private SlackSessionMapper $sessionMapper,
        private InboundEventIdempotencyStore $idempotencyStore,
        private SlackNotifier $notifier,
    ) {
    }

    public function handle(SlackInboundEvent $event): SlackInteractionResult
    {
        if (!$this->mentionDetector->isMentioned($event->text())) {
            return SlackInteractionResult::ignored();
        }

        if (!$this->idempotencyStore->remember('slack:'.$event->eventId())) {
            return SlackInteractionResult::duplicate();
        }

        $session = $this->sessionMapper->map($event);
        $message = sprintf('Accepted. I will continue this Slack thread as session `%s`.', $session->threadId());

        $this->notifier->showTyping($event);
        $this->notifier->postProgress($event, $message);

        return SlackInteractionResult::handled($message);
    }
}
