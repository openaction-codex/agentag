<?php

namespace App\AgentTag\Runner;

final readonly class CodexSessionSubagentInspector implements SubagentSessionInspector
{
    #[\Override]
    public function inspect(string $threadId, string $codexHome): ?SubagentSessionMetadata
    {
        if (!preg_match('/^[A-Za-z0-9-]{8,64}$/', $threadId)) {
            return null;
        }

        $paths = glob(rtrim($codexHome, '/').'/sessions/*/*/*/rollout-*-'.$threadId.'.jsonl') ?: [];
        foreach ($paths as $path) {
            $metadata = $this->metadataFromFile($threadId, $path);
            if (null !== $metadata) {
                return $metadata;
            }
        }

        return null;
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
