<?php

namespace App\AgentTag\Codebase;

final readonly class CodebaseContext
{
    /**
     * @param list<RepositoryClone> $clones
     */
    public function __construct(private array $clones)
    {
    }

    /**
     * @return list<RepositoryClone>
     */
    public function clones(): array
    {
        return $this->clones;
    }

    /**
     * @return array<string, string>
     */
    public function cloneMap(): array
    {
        $map = [];
        foreach ($this->clones as $clone) {
            $map[$clone->repository()->identifier()] = $clone->path();
        }

        return $map;
    }

    public function promptSection(): string
    {
        if ([] === $this->clones) {
            return 'No repository context was requested for this workflow.';
        }

        $lines = [
            'Repository context:',
            'Inspect cloned repositories read-only. Cite relevant file paths when answering.',
        ];

        foreach ($this->clones as $clone) {
            $lines[] = sprintf(
                '- %s: %s',
                $clone->repository()->identifier(),
                $clone->path(),
            );
        }

        return implode("\n", $lines);
    }
}
