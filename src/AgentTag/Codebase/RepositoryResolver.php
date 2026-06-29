<?php

namespace App\AgentTag\Codebase;

use App\AgentTag\Configuration\AgentTagSettings;
use App\AgentTag\Configuration\ConfiguredRepository;

final readonly class RepositoryResolver
{
    public function __construct(private AgentTagSettings $settings)
    {
    }

    /**
     * @return list<ConfiguredRepository>
     */
    public function repositories(): array
    {
        $repositories = [];
        foreach ($this->settings->repositories() as $repository) {
            $repositories[] = $repository;
        }

        return $repositories;
    }
}
