<?php

namespace App\AgentTag\Workflow;

/**
 * @phpstan-type WorkflowData array{
 *     name?: mixed,
 *     description?: mixed,
 *     triggers?: mixed,
 *     tools?: mixed,
 *     repositories?: mixed
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
        private string $description,
        private array $triggers,
        private array $tools,
        private array $repositories,
        private string $sourcePath,
    ) {
    }

    /**
     * @param WorkflowData $data
     */
    public static function fromArray(array $data, string $sourcePath): self
    {
        $fallbackName = pathinfo($sourcePath, \PATHINFO_FILENAME);
        $name = self::optionalString($data['name'] ?? null) ?? $fallbackName;

        if ('' === $name) {
            throw new \InvalidArgumentException(sprintf('Workflow file "%s" must define a name.', $sourcePath));
        }

        return new self(
            $name,
            self::optionalString($data['description'] ?? null) ?? '',
            self::stringList($data['triggers'] ?? []),
            self::stringList($data['tools'] ?? []),
            self::stringList($data['repositories'] ?? []),
            $sourcePath,
        );
    }

    public function name(): string
    {
        return $this->name;
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
            throw new \InvalidArgumentException('Workflow scalar fields must be strings.');
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
