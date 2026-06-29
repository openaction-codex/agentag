<?php

namespace App\Tests\AgentTag\Codebase;

use App\AgentTag\Codebase\RepositoryResolver;
use App\AgentTag\Configuration\AgentTagSettings;
use PHPUnit\Framework\TestCase;

final class RepositoryResolverTest extends TestCase
{
    public function testItReturnsAllConfiguredRepositoriesForTheGenericAgent(): void
    {
        $repositories = $this->resolver()->repositories();

        self::assertCount(2, $repositories);
        self::assertSame('openaction-codex-agentag', $repositories[0]->identifier());
        self::assertSame('git@github.com:openaction-codex/agentag.git', $repositories[0]->url());
        self::assertSame('example-org-api', $repositories[1]->identifier());
    }

    private function resolver(): RepositoryResolver
    {
        return new RepositoryResolver(new AgentTagSettings(
            '@Codex',
            '/tmp/workspace',
            'git@github.com:openaction-codex/agentag.git,git@github.com:example-org/api.git',
        ));
    }
}
