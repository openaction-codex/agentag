<?php

namespace App\AgentTag\Runner;

final class CodexJsonEventParser
{
    private string $buffer = '';

    private ?string $threadId = null;

    /**
     * @return list<AgentRunnerProgress>
     */
    public function consume(string $chunk): array
    {
        $this->buffer .= $chunk;
        $events = [];

        while (false !== $position = strpos($this->buffer, "\n")) {
            $line = substr($this->buffer, 0, $position);
            $this->buffer = substr($this->buffer, $position + 1);
            $event = $this->progressFromLine($line);
            if (null !== $event) {
                $events[] = $event;
            }
        }

        return $events;
    }

    /**
     * @return list<AgentRunnerProgress>
     */
    public function flush(): array
    {
        $line = $this->buffer;
        $this->buffer = '';
        $event = $this->progressFromLine($line);

        return null === $event ? [] : [$event];
    }

    public function lastAgentMessageFromOutput(string $output): ?string
    {
        $lastMessage = null;
        foreach (explode("\n", $output) as $line) {
            $line = trim($line);
            if ('' === $line) {
                continue;
            }

            try {
                $data = json_decode($line, true, flags: \JSON_THROW_ON_ERROR);
            } catch (\JsonException) {
                continue;
            }

            if (!is_array($data)) {
                continue;
            }
            if ('thread.started' === ($data['type'] ?? null) && is_string($data['thread_id'] ?? null)) {
                $this->threadId = $data['thread_id'];
            }

            $message = $this->agentMessageFromData($data);
            if (null !== $message) {
                $lastMessage = $message;
            }
        }

        return $lastMessage;
    }

    public function threadId(): ?string
    {
        return $this->threadId;
    }

    private function progressFromLine(string $line): ?AgentRunnerProgress
    {
        $line = trim($line);
        if ('' === $line) {
            return null;
        }

        try {
            $data = json_decode($line, true, flags: \JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }

        if (!is_array($data)) {
            return null;
        }

        if ('thread.started' === ($data['type'] ?? null) && is_string($data['thread_id'] ?? null)) {
            $this->threadId = $data['thread_id'];

            return null;
        }

        $item = $data['item'] ?? null;
        $subagentStarted = $this->subagentStartedFromItem($item);
        if (null !== $subagentStarted) {
            return $subagentStarted;
        }

        if (is_array($item)) {
            $itemType = $item['type'] ?? null;
            if (!is_string($itemType) || !in_array($itemType, ['agent_message', 'assistant_message'], true)) {
                return null;
            }
        } else {
            $eventType = $data['type'] ?? null;
            if (!is_string($eventType) || !in_array($eventType, ['agent_message', 'assistant_message'], true)) {
                return null;
            }
        }

        $message = $this->messageFromData($data);
        if (null === $message) {
            return null;
        }

        return new AgentRunnerProgress('agent_message', $message);
    }

    private function subagentStartedFromItem(mixed $item): ?AgentRunnerProgress
    {
        if (!is_array($item)
            || 'collab_tool_call' !== ($item['type'] ?? null)
            || 'spawn_agent' !== ($item['tool'] ?? null)
            || 'completed' !== ($item['status'] ?? null)) {
            return null;
        }

        $threadIds = $item['receiver_thread_ids'] ?? null;
        if (!is_array($threadIds)) {
            return null;
        }

        foreach ($threadIds as $threadId) {
            if (is_string($threadId) && '' !== trim($threadId)) {
                return new AgentRunnerProgress(
                    'subagent_started',
                    'Codex started a subagent thread.',
                    ['thread_id' => trim($threadId)],
                );
            }
        }

        return null;
    }

    /**
     * @param array<mixed, mixed> $data
     */
    private function messageFromData(array $data): ?string
    {
        foreach (['message', 'text', 'content', 'summary'] as $key) {
            $value = $data[$key] ?? null;
            if (is_scalar($value) && '' !== trim((string) $value)) {
                return trim((string) $value);
            }
        }

        $item = $data['item'] ?? null;
        if (is_array($item)) {
            $message = $this->messageFromData($item);
            if (null !== $message) {
                return $message;
            }
        }

        $delta = $data['delta'] ?? null;
        if (is_array($delta)) {
            return $this->messageFromData($delta);
        }

        return null;
    }

    /**
     * @param array<mixed, mixed> $data
     */
    private function agentMessageFromData(array $data): ?string
    {
        $type = $data['type'] ?? null;
        if (is_string($type) && in_array($type, ['agent_message', 'assistant_message'], true)) {
            return $this->messageFromData($data);
        }

        $item = $data['item'] ?? null;
        if (is_array($item)) {
            $itemType = $item['type'] ?? null;
            if (is_string($itemType) && in_array($itemType, ['agent_message', 'assistant_message'], true)) {
                return $this->messageFromData($item);
            }
        }

        return null;
    }
}
