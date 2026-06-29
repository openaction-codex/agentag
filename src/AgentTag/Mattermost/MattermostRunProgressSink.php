<?php

namespace App\AgentTag\Mattermost;

use App\AgentTag\Run\RunEventRecorder;
use App\AgentTag\Runner\AgentRunnerProgress;
use App\AgentTag\Runner\AgentRunnerProgressSink;
use App\AgentTag\Runner\AgentRunnerResult;
use App\Entity\AgentRun;
use App\Entity\RunEvent;

final class MattermostRunProgressSink implements AgentRunnerProgressSink
{
    private ?string $lastPostedMessage = null;

    private int $lastPostedAt = 0;

    private int $lastTypingAt = 0;

    public function __construct(
        private readonly MattermostNotifier $notifier,
        private readonly MattermostInboundEvent $event,
        private readonly AgentRun $run,
        private readonly ?RunEventRecorder $runEventRecorder = null,
        private readonly int $minimumIntervalSeconds = 5,
        private readonly int $typingRefreshIntervalSeconds = 2,
    ) {
    }

    #[\Override]
    public function onProgress(AgentRunnerProgress $progress): void
    {
        $message = $this->formatMessage($progress->message());
        if (!$this->shouldPost($message)) {
            return;
        }

        $this->post($message, $progress->type());
    }

    #[\Override]
    public function onHeartbeat(): void
    {
        $this->refreshTyping(false);
    }

    public function finish(AgentRunnerResult $result): void
    {
        $message = $this->formatMessage($result->finalMessage());
        if ('' === $message || $message === $this->lastPostedMessage) {
            return;
        }

        $this->post($message, 'final_message');
    }

    private function shouldPost(string $message): bool
    {
        if ('' === $message || $message === $this->lastPostedMessage) {
            return false;
        }

        return 0 === $this->lastPostedAt || time() - $this->lastPostedAt >= $this->minimumIntervalSeconds;
    }

    private function post(string $message, string $type): void
    {
        $this->refreshTyping(true);
        $this->notifier->postProgress($this->event, $message);
        $this->runEventRecorder?->record($this->run, RunEvent::TYPE_PROGRESS_UPDATE, $message, [
            'platform' => 'mattermost',
            'progress_type' => $type,
            'channel_id' => $this->event->channelId(),
            'thread_id' => '' === $this->event->rootId() ? $this->event->postId() : $this->event->rootId(),
        ]);

        $this->lastPostedMessage = $message;
        $this->lastPostedAt = time();
    }

    private function refreshTyping(bool $force): void
    {
        $now = time();
        if (!$force && 0 !== $this->lastTypingAt && $now - $this->lastTypingAt < $this->typingRefreshIntervalSeconds) {
            return;
        }

        $this->notifier->showTyping($this->event);
        $this->lastTypingAt = $now;
    }

    private function formatMessage(string $message): string
    {
        $message = trim(str_replace(["\r\n", "\r"], "\n", $message));
        $message = preg_replace("/\n{4,}/", "\n\n\n", $message) ?? $message;

        if (strlen($message) <= 4000) {
            return $message;
        }

        $message = rtrim(substr($message, 0, 3997)).'...';
        if (1 === substr_count($message, '```') % 2) {
            $message .= "\n```";
        }

        return $message;
    }
}
