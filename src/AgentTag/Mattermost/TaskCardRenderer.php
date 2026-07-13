<?php

namespace App\AgentTag\Mattermost;

use App\Entity\AgentRun;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final readonly class TaskCardRenderer
{
    public function __construct(
        private UrlGeneratorInterface $urlGenerator,
        private string $appSecret,
    ) {
    }

    public function render(AgentRun $run): TaskCard
    {
        $message = match ($run->status()) {
            AgentRun::STATUS_COMPLETED => $this->completed($run),
            AgentRun::STATUS_FAILED => $this->failed($run),
            AgentRun::STATUS_INTERRUPTED => $this->interrupted($run),
            AgentRun::STATUS_WAITING => $this->active($run, '🔵', $run->waitReason() ?? 'Waiting to continue'),
            default => $this->active($run, '🟡', $run->currentStage() ?? 'Working'),
        };

        return new TaskCard($this->truncate($this->blockquote($message)), $this->actionProps($run));
    }

    private function active(AgentRun $run, string $icon, string $currentStage): string
    {
        $lines = [
            sprintf('%s **%s**', $icon, $run->title()),
            sprintf('Requested by %s · started %s', $this->requester($run), $this->relativeStart($run)),
            $this->modelLine($run),
        ];
        if (null !== $subagentLine = $this->subagentLine($run)) {
            $lines[] = $subagentLine;
        }
        $lines[] = '';

        foreach (array_slice($run->completedStages(), -5) as $stage) {
            $lines[] = '✓ '.$stage;
        }
        $lines[] = '→ '.$currentStage;
        $lines[] = '○ Complete task and verify results';

        return implode("\n", $lines);
    }

    private function completed(AgentRun $run): string
    {
        return $this->finishedTimeline($run, '✅', 'completed in '.$this->duration($run), '✓ Complete task and verify results');
    }

    private function failed(AgentRun $run): string
    {
        return $this->finishedTimeline($run, '❌', 'failed after '.$this->duration($run), '✕ Task failed');
    }

    private function interrupted(AgentRun $run): string
    {
        if (AgentRun::WORKSPACE_CLEANUP_CLEANED === $run->workspaceCleanupState()) {
            return $this->finishedTimeline($run, '⏹️', 'stopped after '.$this->duration($run), '■ Stopped', 'Workspace discarded.');
        }

        return $this->finishedTimeline(
            $run,
            '⏹️',
            'stopped after '.$this->duration($run),
            '■ Stopped',
            sprintf('Workspace preserved until %s.', $run->retainedUntil()?->format('Y-m-d H:i \U\T\C') ?? 'the retention window expires'),
        );
    }

    private function finishedTimeline(AgentRun $run, string $icon, string $timing, string $lastStep, ?string $note = null): string
    {
        $lines = [
            sprintf('%s **%s**', $icon, $run->title()),
            sprintf('Requested by %s · %s', $this->requester($run), $timing),
            $this->modelLine($run),
        ];
        if (null !== $subagentLine = $this->subagentLine($run)) {
            $lines[] = $subagentLine;
        }
        $lines[] = '';

        foreach (array_slice($run->completedStages(), -6) as $stage) {
            $lines[] = '✓ '.$stage;
        }
        $lines[] = $lastStep;
        if (null !== $note) {
            $lines[] = '';
            $lines[] = $note;
        }

        return implode("\n", $lines);
    }

    private function modelLine(AgentRun $run): string
    {
        $selection = $run->modelSelection();
        $delegation = $selection->usesSubagent() ? sprintf(' via `%s`', $selection->agent) : ' in the main agent';

        return sprintf('Model: **%s · %s**%s — %s', $selection->displayModel, $selection->effort, $delegation, $selection->reason);
    }

    private function subagentLine(AgentRun $run): ?string
    {
        $threadId = $run->subagentThreadId();
        if (null === $threadId) {
            return null;
        }

        $selection = $run->modelSelection();
        $agent = $run->subagentAgent();
        $model = $run->subagentModel();
        $effort = $run->subagentReasoningEffort();
        if ($run->subagentMetadataVerified() && null !== $agent && null !== $model && null !== $effort) {
            if ($agent === $selection->agent && $model === $selection->model && $effort === $selection->effort) {
                return sprintf('Agent: ✓ Verified **%s · %s** via `%s` started · thread `%s`', $this->displayModel($model), $effort, $agent, $threadId);
            }

            return sprintf('Agent: ⚠ Started **%s · %s** via `%s`, but the selected route was `%s` · thread `%s`', $this->displayModel($model), $effort, $agent, $selection->agent, $threadId);
        }

        return sprintf('Agent: ✓ Codex reported subagent thread `%s` started; exact profile metadata was unavailable.', $threadId);
    }

    private function displayModel(string $model): string
    {
        return match ($model) {
            'gpt-5.6-luna' => 'GPT-5.6 Luna',
            'gpt-5.6-terra' => 'GPT-5.6 Terra',
            'gpt-5.6-sol' => 'GPT-5.6 Sol',
            default => $model,
        };
    }

    private function blockquote(string $message): string
    {
        return implode("\n", array_map(static fn (string $line): string => '> '.$line, explode("\n", $message)));
    }

    /** @return array<string, mixed> */
    private function actionProps(AgentRun $run): array
    {
        if (!$run->isActive() || ($run->interruptionRequested() && AgentRun::INTERRUPT_CANCEL === $run->interruptionKind())) {
            return ['attachments' => []];
        }

        return ['attachments' => [['actions' => [[
            'id' => 'cancel',
            'name' => 'Stop',
            'style' => 'danger',
            'integration' => [
                'url' => $this->urlGenerator->generate('agentag_mattermost_action', [], UrlGeneratorInterface::ABSOLUTE_URL),
                'context' => [
                    'action' => 'cancel',
                    'run_id' => $run->id(),
                    'signature' => $this->signature((int) $run->id(), 'cancel'),
                ],
            ],
        ]]]]];
    }

    public function signature(int $runId, string $action): string
    {
        return hash_hmac('sha256', $runId.':'.$action, $this->appSecret);
    }

    private function requester(AgentRun $run): string
    {
        $name = $run->requesterName() ?? $run->requesterId() ?? 'unknown';

        return '@'.ltrim($name, '@');
    }

    private function relativeStart(AgentRun $run): string
    {
        $seconds = max(0, time() - ($run->startedAt() ?? $run->createdAt())->getTimestamp());
        if ($seconds < 5) {
            return 'just now';
        }
        if ($seconds < 60) {
            return $seconds.'s ago';
        }

        return intdiv($seconds, 60).'m ago';
    }

    private function duration(AgentRun $run): string
    {
        $start = $run->startedAt() ?? $run->createdAt();
        $end = $run->finishedAt() ?? new \DateTimeImmutable();
        $seconds = max(0, $end->getTimestamp() - $start->getTimestamp());

        return $seconds < 60
            ? $seconds.'s'
            : sprintf('%dm %02ds', intdiv($seconds, 60), $seconds % 60);
    }

    private function truncate(string $message): string
    {
        return strlen($message) <= 4000 ? $message : rtrim(substr($message, 0, 3997)).'...';
    }
}
