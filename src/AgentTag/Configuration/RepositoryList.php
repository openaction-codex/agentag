<?php

namespace App\AgentTag\Configuration;

/**
 * @implements \IteratorAggregate<int, ConfiguredRepository>
 */
final readonly class RepositoryList implements \Countable, \IteratorAggregate
{
    /**
     * @param list<ConfiguredRepository> $repositories
     */
    private function __construct(private array $repositories)
    {
    }

    public static function fromCsv(string $repositoryUrlsCsv): self
    {
        $urls = array_values(array_filter(
            array_map('trim', explode(',', $repositoryUrlsCsv)),
            static fn (string $url): bool => '' !== $url,
        ));

        $repositories = [];
        $seenIdentifiers = [];
        foreach ($urls as $url) {
            $repository = ConfiguredRepository::fromSshUrl($url);

            if (isset($seenIdentifiers[$repository->identifier()])) {
                throw new \InvalidArgumentException(sprintf('Repository identifier "%s" is ambiguous. Use distinct repository paths.', $repository->identifier()));
            }

            $seenIdentifiers[$repository->identifier()] = true;
            $repositories[] = $repository;
        }

        return new self($repositories);
    }

    #[\Override]
    public function count(): int
    {
        return count($this->repositories);
    }

    /**
     * @return \Traversable<int, ConfiguredRepository>
     */
    #[\Override]
    public function getIterator(): \Traversable
    {
        yield from $this->repositories;
    }
}
