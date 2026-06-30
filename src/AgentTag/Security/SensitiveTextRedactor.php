<?php

namespace App\AgentTag\Security;

final readonly class SensitiveTextRedactor
{
    private const SECRET_ASSIGNMENT_PATTERN = '/(?P<key_quote>["\']?)\b(?P<name>authorization|api[_-]?key|access[_-]?token|refresh[_-]?token|token|secret|password|passwd|pwd)\b(?P=key_quote)(?P<spacing>\s*[:=]\s*)(?P<value>"[^"]*"|\'[^\']*\'|[^\s,;}]+)/i';

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

    /**
     * @var list<string>
     */
    private array $customPatterns;

    public function __construct(string $customPatterns = '')
    {
        $this->customPatterns = $this->parseCustomPatterns($customPatterns);
    }

    public function redact(string $text): string
    {
        $text = $this->redactKnownPatterns($text);
        $text = $this->redactCustomPatterns($text);

        $redacted = preg_replace_callback(
            self::SECRET_ASSIGNMENT_PATTERN,
            static fn (array $matches): string => sprintf(
                '%s%s%s%s%s',
                self::stringMatch($matches, 'key_quote'),
                self::stringMatch($matches, 'name'),
                self::stringMatch($matches, 'key_quote'),
                self::stringMatch($matches, 'spacing'),
                self::redactedAssignmentValue(self::stringMatch($matches, 'value')),
            ),
            $text,
        );

        $text = $redacted ?? $text;

        return $this->redactCustomPatterns($this->redactKnownPatterns($text));
    }

    private function redactKnownPatterns(string $text): string
    {
        foreach (self::SECRET_PATTERNS as $pattern => $replacement) {
            $redacted = preg_replace($pattern, $replacement, $text);
            $text = $redacted ?? $text;
        }

        return $text;
    }

    private function redactCustomPatterns(string $text): string
    {
        foreach ($this->customPatterns as $pattern) {
            $redacted = preg_replace($pattern, '[REDACTED]', $text);
            $text = $redacted ?? $text;
        }

        return $text;
    }

    /**
     * @return list<string>
     */
    private function parseCustomPatterns(string $customPatterns): array
    {
        if ('' === trim($customPatterns)) {
            return [];
        }

        $patterns = [];
        foreach ($this->patternStrings($customPatterns) as $pattern) {
            $pattern = trim($pattern);
            if ('' === $pattern) {
                continue;
            }

            $this->assertValidPattern($pattern);
            $patterns[] = $pattern;
        }

        return $patterns;
    }

    /**
     * @return list<string>
     */
    private function patternStrings(string $customPatterns): array
    {
        $customPatterns = trim($customPatterns);
        if (str_starts_with($customPatterns, '[')) {
            $decoded = json_decode($customPatterns, true);
            if (!is_array($decoded)) {
                throw new \InvalidArgumentException('AgentTag redaction patterns JSON must decode to a list of strings.');
            }

            $patterns = [];
            foreach ($decoded as $pattern) {
                if (!is_string($pattern)) {
                    throw new \InvalidArgumentException('AgentTag redaction patterns JSON must contain only strings.');
                }

                $patterns[] = $pattern;
            }

            return $patterns;
        }

        return preg_split('/\R+/', $customPatterns) ?: [];
    }

    private function assertValidPattern(string $pattern): void
    {
        set_error_handler(static fn (): bool => true);
        try {
            $isValid = false !== preg_match($pattern, '');
        } finally {
            restore_error_handler();
        }

        if (!$isValid) {
            throw new \InvalidArgumentException(sprintf('Invalid AgentTag redaction pattern "%s".', $pattern));
        }
    }

    private static function redactedAssignmentValue(string $value): string
    {
        if (str_starts_with($value, '"') && str_ends_with($value, '"')) {
            return '"[REDACTED]"';
        }

        if (str_starts_with($value, "'") && str_ends_with($value, "'")) {
            return "'[REDACTED]'";
        }

        return '[REDACTED]';
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
