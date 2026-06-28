<?php

namespace App\AgentTag\Mattermost;

use App\AgentTag\Chat\ConfiguredTagMentionDetector;
use App\AgentTag\Chat\InboundEventIdempotencyStore;
use App\AgentTag\Session\ChatSessionStore;
use App\AgentTag\Workflow\WorkflowSelector;

final readonly class MattermostInteractionHandler
{
    public function __construct(
        private ConfiguredTagMentionDetector $mentionDetector,
        private MattermostSessionMapper $sessionMapper,
        private InboundEventIdempotencyStore $idempotencyStore,
        private MattermostNotifier $notifier,
        private ChatSessionStore $sessionStore,
        private MattermostThreadContextProvider $threadContextProvider,
        private WorkflowSelector $workflowSelector,
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

        $selection = $this->workflowSelector->select($event->text());
        if (!$selection->isSelected()) {
            $message = $selection->message();
            $this->notifier->showTyping($event);
            $this->notifier->postProgress($event, $message);

            return MattermostInteractionResult::handled($message);
        }

        $workflow = $selection->workflow();
        $session = $this->sessionMapper->map($event);
        $this->sessionStore->recordRun(
            $session,
            sprintf('Mattermost message %s from user %s.', $event->eventId(), $event->userId()),
            $this->threadContextProvider->contextFor($event),
            $workflow,
        );

        $message = sprintf(
            'Accepted workflow `%s`. I will continue this Mattermost thread as session `%s`.',
            $workflow->name(),
            $session->threadId(),
        );

        $this->notifier->showTyping($event);
        $this->notifier->postProgress($event, $message);

        return MattermostInteractionResult::handled($message);
    }
}
