<?php

namespace App\AgentTag\Mattermost;

use App\AgentTag\Run\RunEventRecorder;
use App\AgentTag\Runner\AgentRunnerProgress;
use App\AgentTag\Runner\AgentRunnerProgressSink;
use App\Entity\AgentRun;
use App\Entity\RunEvent;
use Doctrine\ORM\EntityManagerInterface;

final class MattermostRunProgressSink implements AgentRunnerProgressSink
{
    private int $lastUpdatedAt = 0;
    private int $lastTypingAt = 0;

    public function __construct(
        private readonly MattermostNotifier $notifier,
        private readonly MattermostInboundEvent $event,
        private readonly AgentRun $run,
        private readonly TaskCardRenderer $renderer,
        private readonly EntityManagerInterface $entityManager,
        private readonly ?RunEventRecorder $runEventRecorder = null,
        private readonly int $minimumIntervalSeconds = 3,
        private readonly int $typingRefreshIntervalSeconds = 2,
    ) {
    }

    #[\Override]
    public function onProgress(AgentRunnerProgress $progress): void
    {
        if ('subagent_started' === $progress->type()) {
            $this->recordSubagentStarted($progress);

            return;
        }
        if (!in_array($progress->type(), ['agent_message', 'subagent_progress'], true)) {
            return;
        }
        $stage = 'subagent_progress' === $progress->type()
            ? $this->subagentStage($progress->message())
            : $this->stage($progress->message());
        if ('' === $stage || $stage === $this->run->currentStage()) {
            return;
        }

        $this->run->updateStage($stage);
        $this->entityManager->flush();
        $this->runEventRecorder?->record($this->run, RunEvent::TYPE_PROGRESS_UPDATE, $stage, [
            'platform' => 'mattermost',
            'progress_type' => $progress->type(),
        ]);
        if (0 === $this->lastUpdatedAt || time() - $this->lastUpdatedAt >= $this->minimumIntervalSeconds) {
            $this->updateCard();
        }
    }

    private function recordSubagentStarted(AgentRunnerProgress $progress): void
    {
        $context = $progress->context();
        $threadId = $context['thread_id'] ?? null;
        if (!is_string($threadId) || '' === trim($threadId)) {
            return;
        }

        $agent = $context['agent'] ?? null;
        $model = $context['model'] ?? null;
        $reasoningEffort = $context['reasoning_effort'] ?? null;
        $verified = true === ($context['verified'] ?? false);
        $this->run->recordSubagentStarted(
            $threadId,
            is_string($agent) ? $agent : null,
            is_string($model) ? $model : null,
            is_string($reasoningEffort) ? $reasoningEffort : null,
            $verified,
        );
        $this->entityManager->flush();
        $this->runEventRecorder?->record($this->run, RunEvent::TYPE_PROGRESS_UPDATE, 'Codex started a subagent thread.', [
            'platform' => 'mattermost',
            'progress_type' => $progress->type(),
            'thread_id' => $threadId,
            'agent' => $agent,
            'model' => $model,
            'reasoning_effort' => $reasoningEffort,
            'metadata_verified' => $verified,
        ]);
        $this->updateCard();
    }

    #[\Override]
    public function onHeartbeat(): void
    {
        $now = time();
        if (0 !== $this->lastTypingAt && $now - $this->lastTypingAt < $this->typingRefreshIntervalSeconds) {
            return;
        }
        $this->notifier->showTyping($this->event);
        $this->lastTypingAt = $now;
    }

    public function finish(): void
    {
        $this->updateCard();
        $this->publishAnswer();
    }

    public function milestone(string $message): void
    {
        if ('completion' !== $this->run->notificationPreference()) {
            $this->notifier->postProgress($this->event, $message);
        }
    }

    private function updateCard(): void
    {
        $postId = $this->run->taskPostId();
        if (null === $postId) {
            $card = $this->renderer->render($this->run);
            $postId = $this->notifier->createPost($this->event, $card->message, $card->props);
            if (null !== $postId) {
                $this->run->assignTaskPost($postId);
                $this->entityManager->flush();
            }
        } else {
            $card = $this->renderer->render($this->run);
            $this->notifier->updatePost($postId, $card->message, $card->props);
        }
        $this->lastUpdatedAt = time();
    }

    private function publishAnswer(): void
    {
        if (!in_array($this->run->status(), [AgentRun::STATUS_COMPLETED, AgentRun::STATUS_FAILED], true)
            || null !== $this->run->answerPostId()) {
            return;
        }

        $message = trim($this->run->outputSummary() ?? '');
        if ('' === $message) {
            $message = AgentRun::STATUS_COMPLETED === $this->run->status()
                ? 'Task completed.'
                : 'The task could not be completed.';
        }
        $postId = $this->notifier->createPost($this->event, $message);
        if (null !== $postId) {
            $this->run->assignAnswerPost($postId);
            $this->entityManager->flush();
        }
    }

    private function stage(string $message): string
    {
        $message = trim(preg_replace('/\s+/', ' ', $message) ?? $message);
        $sentence = preg_split('/(?<=[.!?])\s+/', $message, 2)[0] ?? $message;

        return substr($sentence, 0, 240);
    }

    private function subagentStage(string $message): string
    {
        $message = trim(preg_replace('/\s+/', ' ', $message) ?? $message);
        if ('' === $message) {
            return '';
        }

        $agent = match ($this->run->subagentAgent()) {
            'sol-xhigh' => 'Sol',
            'terra-max' => 'Terra',
            default => 'Specialist',
        };

        return substr($agent.' — '.$message, 0, 240);
    }
}
