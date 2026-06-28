<?php

namespace App\AgentTag\Memory;

use App\AgentTag\Configuration\AgentTagSettings;
use App\Entity\GlobalMemory;

final readonly class GlobalMemoryCommandHandler
{
    public function __construct(
        private AgentTagSettings $settings,
        private GlobalMemoryService $memoryService,
    ) {
    }

    public function handle(string $message, GlobalMemoryCommandContext $context): ?string
    {
        $request = $this->stripTag($message);

        if (1 === preg_match('/^(?:list|show)?\s*(?:global\s+)?memories\b/i', $request) || 1 === preg_match('/^what do you remember\??$/i', $request)) {
            return $this->formatMemories($this->memoryService->all());
        }

        if (1 === preg_match('/^(?:delete|remove|forget)\s+(?:global\s+)?memory\s+#?(?P<id>\d+)$/i', $request, $matches)) {
            $id = (int) $matches['id'];

            return $this->memoryService->delete($id)
                ? sprintf('Deleted global memory #%d.', $id)
                : sprintf('Global memory #%d was not found.', $id);
        }

        if (1 === preg_match('/^(?:(?:remember)(?:\s+that)?|confirm memory:)\s+(?P<content>.+)$/is', $request, $matches)) {
            $result = $this->memoryService->rememberExplicit((string) $matches['content'], $context);

            return null === $result->memory()
                ? $result->message()
                : sprintf('Stored global memory #%d.', $this->memoryId($result->memory()));
        }

        return null;
    }

    private function stripTag(string $message): string
    {
        $tag = preg_quote($this->settings->tag(), '/');
        $stripped = preg_replace('/(?<![A-Za-z0-9_@])'.$tag.'(?![A-Za-z0-9_-])/i', '', $message, 1);

        return trim($stripped ?? $message);
    }

    /**
     * @param list<GlobalMemory> $memories
     */
    private function formatMemories(array $memories): string
    {
        if ([] === $memories) {
            return 'No explicit global memories are stored.';
        }

        $lines = ['Explicit global memories:'];
        foreach ($memories as $memory) {
            $lines[] = sprintf('- #%d: %s', $this->memoryId($memory), $memory->content());
        }

        return implode("\n", $lines);
    }

    private function memoryId(GlobalMemory $memory): int
    {
        $id = $memory->id();
        if (null === $id) {
            throw new \LogicException('Stored global memories must have an ID.');
        }

        return $id;
    }
}
