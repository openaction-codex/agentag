<?php

namespace App\AgentTag\Runner;

final readonly class CodexSessionSubagentInspector implements SubagentSessionInspector
{
    #[\Override]
    public function inspect(string $threadId, string $codexHome): ?SubagentSessionMetadata
    {
        foreach ($this->paths($threadId, $codexHome) as $path) {
            $metadata = $this->metadataFromFile($threadId, $path);
            if (null !== $metadata) {
                return $metadata;
            }
        }

        return null;
    }

    #[\Override]
    public function progressSince(string $threadId, string $codexHome, int $offset): SubagentSessionProgress
    {
        $paths = $this->paths($threadId, $codexHome);
        $path = $paths[0] ?? null;
        if (null === $path) {
            return new SubagentSessionProgress($offset, []);
        }

        $handle = fopen($path, 'rb');
        if (false === $handle) {
            return new SubagentSessionProgress($offset, []);
        }

        $stat = fstat($handle);
        $size = false === $stat ? null : $stat['size'];
        if (null === $size || $offset < 0 || $offset > $size || 0 !== fseek($handle, $offset)) {
            $offset = 0;
            rewind($handle);
        }

        $nextOffset = $offset;
        $messages = [];
        while (false !== ($lineStart = ftell($handle)) && false !== ($line = fgets($handle))) {
            if (!str_ends_with($line, "\n")) {
                $nextOffset = $lineStart;
                break;
            }
            $position = ftell($handle);
            if (false !== $position) {
                $nextOffset = $position;
            }

            $message = $this->progressMessageFromLine($line);
            if (null !== $message) {
                $messages[] = $message;
            }
        }
        fclose($handle);

        return new SubagentSessionProgress($nextOffset, $messages);
    }

    /** @return list<string> */
    private function paths(string $threadId, string $codexHome): array
    {
        if (!preg_match('/^[A-Za-z0-9-]{8,64}$/', $threadId)) {
            return [];
        }

        $paths = glob(rtrim($codexHome, '/').'/sessions/*/*/*/rollout-*-'.$threadId.'.jsonl') ?: [];
        sort($paths);

        return $paths;
    }

    private function progressMessageFromLine(string $line): ?string
    {
        $data = json_decode($line, true);
        if (!is_array($data) || 'event_msg' !== ($data['type'] ?? null) || !is_array($data['payload'] ?? null)) {
            return null;
        }
        $payload = $data['payload'];
        $message = $payload['message'] ?? null;
        if ('agent_message' !== ($payload['type'] ?? null) || !is_string($message) || '' === trim($message)) {
            return null;
        }

        return trim($message);
    }

    private function metadataFromFile(string $threadId, string $path): ?SubagentSessionMetadata
    {
        $agent = null;
        $model = null;
        $effort = null;
        $file = new \SplFileObject($path);
        foreach ($file as $line) {
            if (!is_string($line) || '' === trim($line)) {
                continue;
            }
            $data = json_decode($line, true);
            if (!is_array($data) || !is_array($data['payload'] ?? null)) {
                continue;
            }
            $payload = $data['payload'];
            if ('session_meta' === ($data['type'] ?? null)) {
                $agent = $this->nestedString($payload, ['agent_role'])
                    ?? $this->nestedString($payload, ['source', 'subagent', 'thread_spawn', 'agent_role'])
                    ?? $agent;
            }
            if ('turn_context' === ($data['type'] ?? null)) {
                $model = $this->nestedString($payload, ['model']) ?? $model;
                $effort = $this->nestedString($payload, ['effort'])
                    ?? $this->nestedString($payload, ['collaboration_mode', 'settings', 'reasoning_effort'])
                    ?? $effort;
            }

            if (null !== $agent && null !== $model && null !== $effort) {
                return new SubagentSessionMetadata($threadId, $agent, $model, $effort);
            }
        }

        return null;
    }

    /**
     * @param array<mixed, mixed> $data
     * @param list<string>        $path
     */
    private function nestedString(array $data, array $path): ?string
    {
        $value = $data;
        foreach ($path as $key) {
            if (!is_array($value) || !array_key_exists($key, $value)) {
                return null;
            }
            $value = $value[$key];
        }

        return is_string($value) && '' !== trim($value) ? trim($value) : null;
    }
}
