<?php

namespace App\AgentTag\Runner;

final readonly class TaskContinuationParser
{
    /** @return array{message: string, continuation: ?TaskContinuation} */
    public function parse(string $message): array
    {
        if (1 !== preg_match('/\s*<!--\s*agentag:(\{.*?\})\s*-->\s*$/s', $message, $matches)) {
            return ['message' => trim($message), 'continuation' => null];
        }

        $data = json_decode($matches[1], true);
        $cleanMessage = trim(substr($message, 0, (int) strpos($message, $matches[0])));
        if (!is_array($data) || 'wait' !== ($data['action'] ?? null)) {
            return ['message' => $cleanMessage, 'continuation' => null];
        }

        $seconds = $data['seconds'] ?? null;
        $reason = $data['reason'] ?? null;
        if (!is_int($seconds) || !is_string($reason) || '' === trim($reason)) {
            return ['message' => $cleanMessage, 'continuation' => null];
        }

        return [
            'message' => $cleanMessage,
            'continuation' => new TaskContinuation(max(30, min($seconds, 86400)), trim($reason)),
        ];
    }
}
