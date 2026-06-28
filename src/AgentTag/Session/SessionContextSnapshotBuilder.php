<?php

namespace App\AgentTag\Session;

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
    public function build(ChatSession $session, ChatThreadContext $threadContext, array $priorRuns): string
    {
        $sections = [
            sprintf('Session: %s', $session->sessionKey()),
            sprintf('Platform: %s', $session->platform()),
            sprintf('Thread: %s', $session->threadId()),
            sprintf('Session summary: %s', $session->summary() ?? '(none)'),
            $this->formatThreadMessages($threadContext),
            $this->formatPriorRuns($priorRuns),
            'Explicit global memories: none configured.',
            'Relevant links/artifacts: none recorded.',
        ];

        return $this->bound(implode("\n\n", $sections));
    }

    private function formatThreadMessages(ChatThreadContext $threadContext): string
    {
        $lines = ['Thread messages:'];

        foreach ($threadContext->messages() as $message) {
            $lines[] = sprintf(
                '- %s (%s): %s',
                $message->authorId(),
                $message->externalId(),
                $this->singleLine($message->text()),
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
                $this->singleLine($run->inputSummary() ?? '(none)'),
                $this->singleLine($run->outputSummary() ?? '(none)'),
            );
        }

        if (1 === count($lines)) {
            $lines[] = '- (none)';
        }

        return implode("\n", $lines);
    }

    private function singleLine(string $value): string
    {
        return preg_replace('/\s+/', ' ', trim($value)) ?? trim($value);
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
