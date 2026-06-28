<?php

namespace App\AgentTag\Slack;

use App\AgentTag\Chat\ConfiguredTagMentionDetector;
use App\AgentTag\Chat\InboundEventIdempotencyStore;
use App\AgentTag\Session\ChatSessionStore;
use App\AgentTag\Workflow\WorkflowSelector;

final readonly class SlackInteractionHandler
{
    public function __construct(
        private ConfiguredTagMentionDetector $mentionDetector,
        private SlackSessionMapper $sessionMapper,
        private InboundEventIdempotencyStore $idempotencyStore,
        private SlackNotifier $notifier,
        private ChatSessionStore $sessionStore,
        private SlackThreadContextProvider $threadContextProvider,
        private WorkflowSelector $workflowSelector,
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

        $selection = $this->workflowSelector->select($event->text());
        if (!$selection->isSelected()) {
            $message = $selection->message();
            $this->notifier->showTyping($event);
            $this->notifier->postProgress($event, $message);

            return SlackInteractionResult::handled($message);
        }

        $workflow = $selection->workflow();
        $session = $this->sessionMapper->map($event);
        $this->sessionStore->recordRun(
            $session,
            sprintf('Slack event %s from user %s.', $event->eventId(), $event->userId()),
            $this->threadContextProvider->contextFor($event),
            $workflow,
        );

        $message = sprintf(
            'Accepted workflow `%s`. I will continue this Slack thread as session `%s`.',
            $workflow->name(),
            $session->threadId(),
        );

        $this->notifier->showTyping($event);
        $this->notifier->postProgress($event, $message);

        return SlackInteractionResult::handled($message);
    }
}
