<?php

namespace App\AgentTag\Session;

use App\AgentTag\Agent\AgentProfile;
use App\Entity\AgentRun;
use App\Entity\ChatSession;

final readonly class SessionContextSnapshotBuilder
{
    public function __construct(private int $maxCharacters)
    {
        if ($maxCharacters < 1000) {
            throw new \InvalidArgumentException('AgentTag context max characters must be at least 1000.');
        }
    }

    /**
     * @param list<AgentRun> $priorRuns
     */
    public function build(
        ChatSession $session,
        ChatThreadContext $threadContext,
        array $priorRuns,
        AgentProfile $agent,
    ): string {
        $sections = [
            sprintf('Session: %s', $session->sessionKey()),
            sprintf('Thread: %s', $session->threadId()),
            sprintf('Agent: %s', $agent->name()),
            sprintf('Workspace template: %s', $agent->workspacePath()),
            sprintf('Workspace revision: %s', $agent->workspaceRevision() ?? '(none)'),
            sprintf('Session workspace: %s', $session->workspacePath() ?? '(not prepared)'),
            $this->formatThreadMessages($threadContext),
            $this->formatPriorRuns($priorRuns),
            'Relevant links/artifacts: none recorded.',
        ];

        return $this->bound(implode("\n\n", $sections));
    }

    private function formatThreadMessages(ChatThreadContext $threadContext): string
    {
        $lines = ['Thread messages:'];

        foreach ($threadContext->messages() as $message) {
            if ($this->isTaskCard($message->text())) {
                continue;
            }
            $lines[] = sprintf(
                '- %s (%s): %s',
                $message->authorId(),
                $message->externalId(),
                $this->singleLine($message->text(), 3000),
            );
        }

        if (1 === count($lines)) {
            $lines[] = '- (none)';
        }

        return implode("\n", $lines);
    }

    /**
     * @param list<AgentRun> $priorRuns
     */
    private function formatPriorRuns(array $priorRuns): string
    {
        $lines = ['Prior run summaries:'];

        foreach (array_reverse($priorRuns) as $run) {
            $lines[] = sprintf(
                '- %s: input=%s output=%s',
                $run->createdAt()->format(\DateTimeInterface::ATOM),
                $this->singleLine($run->inputSummary() ?? '(none)', 500),
                $this->singleLine($run->outputSummary() ?? '(none)', 1500),
            );
        }

        if (1 === count($lines)) {
            $lines[] = '- (none)';
        }

        return implode("\n", $lines);
    }

    private function singleLine(string $value, ?int $maxCharacters = null): string
    {
        $value = preg_replace('/\s+/', ' ', trim($value)) ?? trim($value);
        if (null !== $maxCharacters && mb_strlen($value) > $maxCharacters) {
            $value = rtrim(mb_substr($value, 0, max(0, $maxCharacters - 1))).'…';
        }

        return $value;
    }

    private function isTaskCard(string $message): bool
    {
        return 1 === preg_match('/^>\s*(?:🟡|🔵|✅|❌|⏹️)\s+\*\*/u', trim($message));
    }

    private function bound(string $snapshot): string
    {
        if (strlen($snapshot) <= $this->maxCharacters) {
            return $snapshot;
        }

        $notice = sprintf("\n[Context truncated to %d characters.]", $this->maxCharacters);
        $availableCharacters = max(0, $this->maxCharacters - strlen($notice));

        return substr($snapshot, 0, $availableCharacters).$notice;
    }
}
