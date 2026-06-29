<?php

namespace App\AgentTag\Mattermost;

use App\AgentTag\Agent\AgentProfileProvider;
use App\AgentTag\Chat\ConfiguredTagMentionDetector;
use App\AgentTag\Chat\InboundEventIdempotencyStore;
use App\AgentTag\Memory\GlobalMemoryCommandContext;
use App\AgentTag\Memory\GlobalMemoryCommandHandler;
use App\AgentTag\Session\ChatSessionStore;
use App\Message\RunAgentRunMessage;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\MessageBusInterface;

final readonly class MattermostInteractionHandler
{
    public function __construct(
        private ConfiguredTagMentionDetector $mentionDetector,
        private MattermostSessionMapper $sessionMapper,
        private InboundEventIdempotencyStore $idempotencyStore,
        private MattermostNotifier $notifier,
        private ChatSessionStore $sessionStore,
        private MattermostThreadContextProvider $threadContextProvider,
        private AgentProfileProvider $agentProfileProvider,
        private MessageBusInterface $messageBus,
        private ?GlobalMemoryCommandHandler $memoryCommandHandler = null,
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

        $session = $this->sessionMapper->map($event);
        try {
            $agent = $this->agentProfileProvider->profile();
            $run = $this->sessionStore->recordRun(
                $session,
                sprintf('Mattermost message %s from user %s.', $event->eventId(), $event->userId()),
                $this->threadContextProvider->contextFor($event),
                $agent,
                $event->eventId(),
                $event->userId(),
            );
        } catch (\InvalidArgumentException|\RuntimeException $exception) {
            $message = $exception->getMessage();
            $this->notifier->showTyping($event);
            $this->notifier->postProgress($event, $message);
            $this->logger?->warning('Rejected Mattermost run during configuration validation.', [
                'event_id' => $event->eventId(),
                'error' => $message,
            ]);

            return MattermostInteractionResult::handled($message);
        }

        $runId = $run->id();
        if (null === $runId) {
            throw new \LogicException('Recorded Mattermost runs must have an id before dispatch.');
        }

        $this->notifier->showTyping($event);
        $this->messageBus->dispatch(new RunAgentRunMessage($runId));
        $this->logger?->info('Queued Mattermost agent run.', [
            'run_id' => $run->id(),
            'event_id' => $event->eventId(),
            'thread_id' => $session->threadId(),
        ]);

        return MattermostInteractionResult::handled('');
    }
}
