<?php

namespace App\AgentTag\Security;

final readonly class SensitiveTextRedactor
{
    private const SECRET_ASSIGNMENT_PATTERN = '/\b(?P<name>authorization|api[_-]?key|access[_-]?token|refresh[_-]?token|token|secret|password|passwd|pwd)\b(?P<spacing>\s*[:=]\s*)(?P<value>"[^"]*"|\'[^\']*\'|[^\s,;]+)/i';

    /**
     * @var array<string, string>
     */
    private const SECRET_PATTERNS = [
        '/\bBearer\s+[A-Za-z0-9._~+\/=-]{8,}\b/i' => 'Bearer [REDACTED]',
        '/\bgh[pousr]_[A-Za-z0-9_]{20,}\b/' => '[REDACTED_GITHUB_TOKEN]',
        '/\bxox[baprs]-[A-Za-z0-9-]{10,}\b/' => '[REDACTED_SLACK_TOKEN]',
        '/\bAKIA[0-9A-Z]{16}\b/' => '[REDACTED_AWS_ACCESS_KEY]',
        '/-----BEGIN [A-Z ]*PRIVATE KEY-----.*?-----END [A-Z ]*PRIVATE KEY-----/s' => '[REDACTED_PRIVATE_KEY]',
    ];

    public function redact(string $text): string
    {
        $text = $this->redactKnownPatterns($text);

        $redacted = preg_replace_callback(
            self::SECRET_ASSIGNMENT_PATTERN,
            static fn (array $matches): string => sprintf(
                '%s%s[REDACTED]',
                self::stringMatch($matches, 'name'),
                self::stringMatch($matches, 'spacing'),
            ),
            $text,
        );

        $text = $redacted ?? $text;

        return $this->redactKnownPatterns($text);
    }

    private function redactKnownPatterns(string $text): string
    {
        foreach (self::SECRET_PATTERNS as $pattern => $replacement) {
            $redacted = preg_replace($pattern, $replacement, $text);
            $text = $redacted ?? $text;
        }

        return $text;
    }

    /**
     * @param array<int|string, mixed> $matches
     */
    private static function stringMatch(array $matches, string $key): string
    {
        $value = $matches[$key] ?? '';

        return is_string($value) ? $value : '';
    }
}
