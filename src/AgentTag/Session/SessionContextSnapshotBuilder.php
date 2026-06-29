<?php

namespace App\AgentTag\Session;

use App\AgentTag\Agent\AgentProfile;
use App\AgentTag\Memory\GlobalMemoryService;
use App\AgentTag\Tool\ToolDefinition;
use App\Entity\AgentRun;
use App\Entity\ChatSession;
use App\Entity\GlobalMemory;

final readonly class SessionContextSnapshotBuilder
{
    public function __construct(
        private int $maxCharacters,
        private ?GlobalMemoryService $globalMemoryService = null,
    ) {
        if ($maxCharacters < 1000) {
            throw new \InvalidArgumentException('AgentTag context max characters must be at least 1000.');
        }
    }

    /**
     * @param list<AgentRun>       $priorRuns
     * @param list<ToolDefinition> $tools
     */
    public function build(
        ChatSession $session,
        ChatThreadContext $threadContext,
        array $priorRuns,
        AgentProfile $agent,
        array $tools,
    ): string {
        $sections = [
            sprintf('Session: %s', $session->sessionKey()),
            sprintf('Platform: %s', $session->platform()),
            sprintf('Thread: %s', $session->threadId()),
            sprintf('Agent: %s', $agent->name()),
            sprintf('Workspace template: %s', $agent->workspacePath()),
            sprintf('Workspace revision: %s', $agent->workspaceRevision() ?? '(none)'),
            sprintf('Session workspace: %s', $session->workspacePath() ?? '(not prepared)'),
            sprintf('Session summary: %s', $session->summary() ?? '(none)'),
            $this->formatTools($tools),
            $this->formatThreadMessages($threadContext),
            $this->formatPriorRuns($priorRuns),
            $this->formatGlobalMemories(),
            'Relevant links/artifacts: none recorded.',
        ];

        return $this->bound(implode("\n\n", $sections));
    }

    /**
     * @param list<ToolDefinition> $tools
     */
    private function formatTools(array $tools): string
    {
        $lines = ['Available tools:'];

        foreach ($tools as $tool) {
            $lines[] = sprintf(
                '- %s (%s, %s, confirmation=%s, sandbox=%s)',
                $tool->name(),
                $tool->type(),
                $tool->sensitivity(),
                $tool->confirmationPolicy(),
                $tool->sandbox(),
            );
        }

        if (1 === count($lines)) {
            $lines[] = '- (none)';
        }

        return implode("\n", $lines);
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

    private function formatGlobalMemories(): string
    {
        $lines = ['Explicit global memories:'];
        $memories = $this->globalMemoryService?->all() ?? [];

        foreach ($memories as $memory) {
            $lines[] = $this->formatGlobalMemory($memory);
        }

        if (1 === count($lines)) {
            $lines[] = '- (none)';
        }

        return implode("\n", $lines);
    }

    private function formatGlobalMemory(GlobalMemory $memory): string
    {
        $id = $memory->id();
        if (null === $id) {
            throw new \LogicException('Stored global memories must have an ID.');
        }

        return sprintf('- #%d: %s', $id, $this->singleLine($memory->content()));
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
