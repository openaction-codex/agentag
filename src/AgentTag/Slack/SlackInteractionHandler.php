<?php

namespace App\AgentTag\Slack;

use App\AgentTag\Agent\AgentProfileProvider;
use App\AgentTag\Chat\ConfiguredTagMentionDetector;
use App\AgentTag\Chat\InboundEventIdempotencyStore;
use App\AgentTag\Memory\GlobalMemoryCommandContext;
use App\AgentTag\Memory\GlobalMemoryCommandHandler;
use App\AgentTag\Run\RunEventRecorder;
use App\AgentTag\Session\ChatSessionStore;
use App\Entity\RunEvent;
use Psr\Log\LoggerInterface;

final readonly class SlackInteractionHandler
{
    public function __construct(
        private ConfiguredTagMentionDetector $mentionDetector,
        private SlackSessionMapper $sessionMapper,
        private InboundEventIdempotencyStore $idempotencyStore,
        private SlackNotifier $notifier,
        private ChatSessionStore $sessionStore,
        private SlackThreadContextProvider $threadContextProvider,
        private AgentProfileProvider $agentProfileProvider,
        private ?GlobalMemoryCommandHandler $memoryCommandHandler = null,
        private ?RunEventRecorder $runEventRecorder = null,
        private ?LoggerInterface $logger = null,
    ) {
    }

    public function handle(SlackInboundEvent $event): SlackInteractionResult
    {
        $this->logger?->info('Received Slack event.', [
            'event_id' => $event->eventId(),
            'team_id' => $event->teamId(),
            'channel_id' => $event->channelId(),
            'event_ts' => $event->eventTs(),
            'thread_ts' => $event->threadTs(),
            'user_id' => $event->userId(),
        ]);

        if (!$this->mentionDetector->isMentioned($event->text())) {
            return SlackInteractionResult::ignored();
        }

        if (!$this->idempotencyStore->remember('slack:'.$event->eventId())) {
            return SlackInteractionResult::duplicate();
        }

        if (null !== $this->memoryCommandHandler) {
            $memoryMessage = $this->memoryCommandHandler->handle($event->text(), new GlobalMemoryCommandContext(
                'slack',
                $event->userId(),
                '' === $event->threadTs() ? $event->eventTs() : $event->threadTs(),
                $event->eventTs(),
            ));
            if (null !== $memoryMessage) {
                $this->notifier->showTyping($event);
                $this->notifier->postProgress($event, $memoryMessage);

                return SlackInteractionResult::handled($memoryMessage);
            }
        }

        $session = $this->sessionMapper->map($event);
        try {
            $agent = $this->agentProfileProvider->profile();
            $run = $this->sessionStore->recordRun(
                $session,
                sprintf('Slack event %s from user %s.', $event->eventId(), $event->userId()),
                $this->threadContextProvider->contextFor($event),
                $agent,
                $event->eventId(),
                $event->userId(),
            );
        } catch (\InvalidArgumentException|\RuntimeException $exception) {
            $message = $exception->getMessage();
            $this->notifier->showTyping($event);
            $this->notifier->postProgress($event, $message);
            $this->logger?->warning('Rejected Slack run during configuration validation.', [
                'event_id' => $event->eventId(),
                'error' => $message,
            ]);

            return SlackInteractionResult::handled($message);
        }

        $message = sprintf(
            'Accepted by the generic agent. I will continue this Slack thread as session `%s`.',
            $session->threadId(),
        );

        $this->notifier->showTyping($event);
        $this->notifier->postProgress($event, $message);
        $this->runEventRecorder?->record($run, RunEvent::TYPE_PROGRESS_UPDATE, $message, [
            'platform' => 'slack',
            'event_id' => $event->eventId(),
            'channel_id' => $event->channelId(),
            'thread_id' => $session->threadId(),
        ]);
        $this->logger?->info('Posted Slack progress update.', [
            'run_id' => $run->id(),
            'event_id' => $event->eventId(),
            'thread_id' => $session->threadId(),
        ]);

        return SlackInteractionResult::handled($message);
    }
}
