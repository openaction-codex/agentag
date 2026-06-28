<?php

namespace App\AgentTag\Workflow;

/**
 * @phpstan-type WorkflowData array{
 *     name?: mixed,
 *     version?: mixed,
 *     description?: mixed,
 *     triggers?: mixed,
 *     tools?: mixed,
 *     allowed_tools?: mixed,
 *     repositories?: mixed,
 *     instructions?: mixed,
 *     output_template?: mixed,
 *     runner_mode?: mixed,
 *     timeout_seconds?: mixed,
 *     sensitivity_policy?: mixed,
 *     default?: mixed
 * }
 */
final readonly class WorkflowDefinition
{
    /**
     * @param list<string> $triggers
     * @param list<string> $tools
     * @param list<string> $repositories
     */
    private function __construct(
        private string $name,
        private ?string $version,
        private string $description,
        private array $triggers,
        private array $tools,
        private array $repositories,
        private string $instructions,
        private string $outputTemplate,
        private string $runnerMode,
        private ?int $timeoutSeconds,
        private string $sensitivityPolicy,
        private bool $default,
        private string $sourcePath,
        private ?string $revision,
    ) {
    }

    /**
     * @param WorkflowData $data
     */
    public static function fromArray(array $data, string $sourcePath, ?string $revision = null): self
    {
        $fallbackName = pathinfo($sourcePath, \PATHINFO_FILENAME);
        $name = self::optionalString($data['name'] ?? null) ?? $fallbackName;

        if ('' === $name) {
            throw new \InvalidArgumentException(sprintf('Workflow file "%s" must define a name.', $sourcePath));
        }

        return new self(
            $name,
            self::optionalString($data['version'] ?? null),
            self::optionalString($data['description'] ?? null) ?? '',
            self::stringList($data['triggers'] ?? []),
            self::stringList($data['tools'] ?? $data['allowed_tools'] ?? []),
            self::stringList($data['repositories'] ?? []),
            self::optionalString($data['instructions'] ?? null) ?? '',
            self::optionalString($data['output_template'] ?? null) ?? '',
            self::optionalString($data['runner_mode'] ?? null) ?? 'codex',
            self::optionalPositiveInt($data['timeout_seconds'] ?? null),
            self::optionalString($data['sensitivity_policy'] ?? null) ?? 'standard',
            self::optionalBool($data['default'] ?? null),
            $sourcePath,
            $revision,
        );
    }

    public function name(): string
    {
        return $this->name;
    }

    public function version(): ?string
    {
        return $this->version;
    }

    public function description(): string
    {
        return $this->description;
    }

    /**
     * @return list<string>
     */
    public function triggers(): array
    {
        return $this->triggers;
    }

    /**
     * @return list<string>
     */
    public function tools(): array
    {
        return $this->tools;
    }

    /**
     * @return list<string>
     */
    public function repositories(): array
    {
        return $this->repositories;
    }

    public function instructions(): string
    {
        return $this->instructions;
    }

    public function outputTemplate(): string
    {
        return $this->outputTemplate;
    }

    public function runnerMode(): string
    {
        return $this->runnerMode;
    }

    public function timeoutSeconds(): ?int
    {
        return $this->timeoutSeconds;
    }

    public function sensitivityPolicy(): string
    {
        return $this->sensitivityPolicy;
    }

    public function default(): bool
    {
        return $this->default;
    }

    public function sourcePath(): string
    {
        return $this->sourcePath;
    }

    public function revision(): ?string
    {
        return $this->revision;
    }

    private static function optionalString(mixed $value): ?string
    {
        if (null === $value) {
            return null;
        }

        if (!is_string($value)) {
            throw new \InvalidArgumentException('Workflow scalar fields must be strings.');
        }

        return trim($value);
    }

    private static function optionalBool(mixed $value): bool
    {
        if (null === $value) {
            return false;
        }

        if (!is_bool($value)) {
            throw new \InvalidArgumentException('Workflow boolean fields must be booleans.');
        }

        return $value;
    }

    private static function optionalPositiveInt(mixed $value): ?int
    {
        if (null === $value) {
            return null;
        }

        if (!is_int($value) || $value < 1) {
            throw new \InvalidArgumentException('Workflow timeout fields must be positive integers.');
        }

        return $value;
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
            throw new \InvalidArgumentException('Workflow list fields must be arrays of strings.');
        }

        $strings = [];
        foreach ($value as $item) {
            if (!is_string($item) || '' === trim($item)) {
                throw new \InvalidArgumentException('Workflow list fields must contain non-empty strings.');
            }

            $strings[] = trim($item);
        }

        return $strings;
    }
}
