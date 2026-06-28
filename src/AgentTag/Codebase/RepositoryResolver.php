<?php

namespace App\AgentTag\Codebase;

use App\AgentTag\Configuration\AgentTagSettings;
use App\AgentTag\Configuration\ConfiguredRepository;
use App\AgentTag\Workflow\WorkflowDefinition;

final readonly class RepositoryResolver
{
    public function __construct(private AgentTagSettings $settings)
    {
    }

    /**
     * @return list<ConfiguredRepository>
     */
    public function repositoriesFor(WorkflowDefinition $workflow): array
    {
        $configured = $this->configuredByIdentifier();
        $requested = $workflow->repositories();

        if ([] === $requested) {
            return [];
        }

        if (in_array('*', $requested, true)) {
            return array_values($configured);
        }

        $repositories = [];
        foreach ($requested as $identifier) {
            $repository = $configured[$identifier] ?? null;
            if (!$repository instanceof ConfiguredRepository) {
                throw new UnknownRepositoryException(sprintf('Unknown repository `%s`. Available repositories: %s.', $identifier, $this->availableRepositoryList(array_values($configured))));
            }

            $repositories[] = $repository;
        }

        return $repositories;
    }

    /**
     * @return array<string, ConfiguredRepository>
     */
    private function configuredByIdentifier(): array
    {
        $repositories = [];
        foreach ($this->settings->repositories() as $repository) {
            $repositories[$repository->identifier()] = $repository;
        }

        return $repositories;
    }

    /**
     * @param list<ConfiguredRepository> $repositories
     */
    private function availableRepositoryList(array $repositories): string
    {
        if ([] === $repositories) {
            return '(none configured)';
        }

        $identifiers = array_map(static fn (ConfiguredRepository $repository): string => '`'.$repository->identifier().'`', $repositories);
        sort($identifiers);

        return implode(', ', $identifiers);
    }
}
