<?php

namespace App\AgentTag\Tool;

/**
 * @phpstan-type ToolData array{
 *     name?: mixed,
 *     type?: mixed,
 *     command?: mixed,
 *     arguments?: mixed,
 *     server?: mixed,
 *     working_directory?: mixed,
 *     environment?: mixed,
 *     env?: mixed,
 *     timeout_seconds?: mixed,
 *     sensitivity?: mixed,
 *     confirmation_policy?: mixed,
 *     sandbox?: mixed
 * }
 */
final readonly class ToolDefinition
{
    public const TYPE_CLI = 'cli';
    public const TYPE_MCP = 'mcp';
    public const SENSITIVITY_NON_SENSITIVE = 'non_sensitive';
    public const SENSITIVITY_SENSITIVE = 'sensitive';
    public const SENSITIVITY_DESTRUCTIVE = 'destructive';
    public const CONFIRMATION_DEFAULT = 'default';
    public const CONFIRMATION_ALWAYS = 'always';
    public const SANDBOX_DEFAULT = 'default';
    public const SANDBOX_NO_SANDBOX = 'no_sandbox';

    /**
     * @param list<string> $arguments
     * @param list<string> $environmentWhitelist
     */
    private function __construct(
        private string $name,
        private string $type,
        private ?string $command,
        private array $arguments,
        private ?string $server,
        private string $workingDirectory,
        private array $environmentWhitelist,
        private int $timeoutSeconds,
        private string $sensitivity,
        private string $confirmationPolicy,
        private string $sandbox,
        private string $sourcePath,
    ) {
    }

    /**
     * @param ToolData $data
     */
    public static function fromArray(array $data, string $sourcePath): self
    {
        $name = self::optionalString($data['name'] ?? null) ?? pathinfo($sourcePath, \PATHINFO_FILENAME);
        if ('' === $name) {
            throw new \InvalidArgumentException(sprintf('Tool file "%s" must define a name.', $sourcePath));
        }

        $type = self::oneOf(self::optionalString($data['type'] ?? null) ?? self::TYPE_CLI, [
            self::TYPE_CLI,
            self::TYPE_MCP,
        ], 'type');
        $command = self::optionalString($data['command'] ?? null);
        $server = self::optionalString($data['server'] ?? null);

        if (self::TYPE_CLI === $type && (null === $command || '' === $command)) {
            throw new \InvalidArgumentException(sprintf('CLI tool "%s" must define a command.', $name));
        }

        if (self::TYPE_MCP === $type && (null === $server || '' === $server)) {
            throw new \InvalidArgumentException(sprintf('MCP tool "%s" must define a server.', $name));
        }

        return new self(
            $name,
            $type,
            $command,
            self::stringList($data['arguments'] ?? []),
            $server,
            self::oneOf(self::optionalString($data['working_directory'] ?? null) ?? 'run', ['workspace', 'run', 'codebase', 'none'], 'working_directory'),
            self::stringList($data['environment'] ?? $data['env'] ?? []),
            self::positiveInt($data['timeout_seconds'] ?? 120, 'timeout_seconds'),
            self::oneOf(self::optionalString($data['sensitivity'] ?? null) ?? self::SENSITIVITY_NON_SENSITIVE, [
                self::SENSITIVITY_NON_SENSITIVE,
                self::SENSITIVITY_SENSITIVE,
                self::SENSITIVITY_DESTRUCTIVE,
            ], 'sensitivity'),
            self::oneOf(self::optionalString($data['confirmation_policy'] ?? null) ?? self::CONFIRMATION_DEFAULT, [
                self::CONFIRMATION_DEFAULT,
                self::CONFIRMATION_ALWAYS,
            ], 'confirmation_policy'),
            self::oneOf(self::optionalString($data['sandbox'] ?? null) ?? self::SANDBOX_DEFAULT, [
                self::SANDBOX_DEFAULT,
                self::SANDBOX_NO_SANDBOX,
            ], 'sandbox'),
            $sourcePath,
        );
    }

    public function name(): string
    {
        return $this->name;
    }

    public function type(): string
    {
        return $this->type;
    }

    public function command(): ?string
    {
        return $this->command;
    }

    /**
     * @return list<string>
     */
    public function arguments(): array
    {
        return $this->arguments;
    }

    public function server(): ?string
    {
        return $this->server;
    }

    public function workingDirectory(): string
    {
        return $this->workingDirectory;
    }

    /**
     * @return list<string>
     */
    public function environmentWhitelist(): array
    {
        return $this->environmentWhitelist;
    }

    public function timeoutSeconds(): int
    {
        return $this->timeoutSeconds;
    }

    public function sensitivity(): string
    {
        return $this->sensitivity;
    }

    public function confirmationPolicy(): string
    {
        return $this->confirmationPolicy;
    }

    public function sandbox(): string
    {
        return $this->sandbox;
    }

    public function sourcePath(): string
    {
        return $this->sourcePath;
    }

    private static function optionalString(mixed $value): ?string
    {
        if (null === $value) {
            return null;
        }

        if (!is_string($value)) {
            throw new \InvalidArgumentException('Tool scalar fields must be strings.');
        }

        return trim($value);
    }

    /**
     * @return list<string>
     */
    private static function stringList(mixed $value): array
    {
        if (null === $value) {
            return [];
        }

        if (!is_array($value)) {
            throw new \InvalidArgumentException('Tool list fields must be arrays of strings.');
        }

        $strings = [];
        foreach ($value as $item) {
            if (!is_string($item) || '' === trim($item)) {
                throw new \InvalidArgumentException('Tool list fields must contain non-empty strings.');
            }

            $strings[] = trim($item);
        }

        return $strings;
    }

    /**
     * @param list<string> $allowedValues
     */
    private static function oneOf(string $value, array $allowedValues, string $field): string
    {
        if (!in_array($value, $allowedValues, true)) {
            throw new \InvalidArgumentException(sprintf('Tool field "%s" has invalid value "%s".', $field, $value));
        }

        return $value;
    }

    private static function positiveInt(mixed $value, string $field): int
    {
        if (!is_int($value) || $value < 1) {
            throw new \InvalidArgumentException(sprintf('Tool field "%s" must be a positive integer.', $field));
        }

        return $value;
    }
}
