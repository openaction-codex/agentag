<?php

namespace App\AgentTag\Mattermost;

use App\AgentTag\Chat\ConfiguredTagMentionDetector;
use App\AgentTag\Chat\InboundEventIdempotencyStore;
use App\AgentTag\Memory\GlobalMemoryCommandContext;
use App\AgentTag\Memory\GlobalMemoryCommandHandler;
use App\AgentTag\Run\RunEventRecorder;
use App\AgentTag\Session\ChatSessionStore;
use App\AgentTag\Workflow\WorkflowSelector;
use App\Entity\RunEvent;
use Psr\Log\LoggerInterface;

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
        private ?GlobalMemoryCommandHandler $memoryCommandHandler = null,
        private ?RunEventRecorder $runEventRecorder = null,
        private ?LoggerInterface $logger = null,
    ) {
    }

    public function handle(MattermostInboundEvent $event): MattermostInteractionResult
    {
        $this->logger?->info('Received Mattermost event.', [
            'event_id' => $event->eventId(),
            'team_id' => $event->teamId(),
            'channel_id' => $event->channelId(),
            'post_id' => $event->postId(),
            'root_id' => $event->rootId(),
            'user_id' => $event->userId(),
        ]);

        if (!$this->mentionDetector->isMentioned($event->text())) {
            return MattermostInteractionResult::ignored();
        }

        if (!$this->idempotencyStore->remember('mattermost:'.$event->eventId())) {
            return MattermostInteractionResult::duplicate();
        }

        if (null !== $this->memoryCommandHandler) {
            $memoryMessage = $this->memoryCommandHandler->handle($event->text(), new GlobalMemoryCommandContext(
                'mattermost',
                $event->userId(),
                '' === $event->rootId() ? $event->postId() : $event->rootId(),
                $event->postId(),
            ));
            if (null !== $memoryMessage) {
                $this->notifier->showTyping($event);
                $this->notifier->postProgress($event, $memoryMessage);

                return MattermostInteractionResult::handled($memoryMessage);
            }
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
        try {
            $run = $this->sessionStore->recordRun(
                $session,
                sprintf('Mattermost message %s from user %s.', $event->eventId(), $event->userId()),
                $this->threadContextProvider->contextFor($event),
                $workflow,
                $event->eventId(),
                $event->userId(),
            );
        } catch (\InvalidArgumentException $exception) {
            $message = $exception->getMessage();
            $this->notifier->showTyping($event);
            $this->notifier->postProgress($event, $message);
            $this->logger?->warning('Rejected Mattermost run during configuration validation.', [
                'event_id' => $event->eventId(),
                'workflow' => $workflow->name(),
                'error' => $message,
            ]);

            return MattermostInteractionResult::handled($message);
        }

        $message = sprintf(
            'Accepted workflow `%s`. I will continue this Mattermost thread as session `%s`.',
            $workflow->name(),
            $session->threadId(),
        );

        $this->notifier->showTyping($event);
        $this->notifier->postProgress($event, $message);
        $this->runEventRecorder?->record($run, RunEvent::TYPE_PROGRESS_UPDATE, $message, [
            'platform' => 'mattermost',
            'event_id' => $event->eventId(),
            'channel_id' => $event->channelId(),
            'thread_id' => $session->threadId(),
        ]);
        $this->logger?->info('Posted Mattermost progress update.', [
            'run_id' => $run->id(),
            'event_id' => $event->eventId(),
            'thread_id' => $session->threadId(),
        ]);

        return MattermostInteractionResult::handled($message);
    }
}
