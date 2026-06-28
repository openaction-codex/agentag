<?php

namespace App\Tests\AgentTag\Codebase;

use App\AgentTag\Codebase\RepositoryResolver;
use App\AgentTag\Codebase\UnknownRepositoryException;
use App\AgentTag\Configuration\AgentTagSettings;
use App\AgentTag\Workflow\WorkflowDefinition;
use PHPUnit\Framework\TestCase;

final class RepositoryResolverTest extends TestCase
{
    public function testItResolvesWorkflowRepositoriesByIdentifier(): void
    {
        $workflow = WorkflowDefinition::fromArray([
            'name' => 'developer',
            'repositories' => ['openaction-codex-agentag'],
        ], '/tmp/developer.yaml');

        $repositories = $this->resolver()->repositoriesFor($workflow);

        self::assertCount(1, $repositories);
        self::assertSame('openaction-codex-agentag', $repositories[0]->identifier());
        self::assertSame('git@github.com:openaction-codex/agentag.git', $repositories[0]->url());
    }

    public function testItResolvesWildcardRepositories(): void
    {
        $workflow = WorkflowDefinition::fromArray([
            'name' => 'developer',
            'repositories' => ['*'],
        ], '/tmp/developer.yaml');

        $repositories = $this->resolver()->repositoriesFor($workflow);

        self::assertCount(2, $repositories);
    }

    public function testItReturnsClearMessageForUnknownRepositories(): void
    {
        $workflow = WorkflowDefinition::fromArray([
            'name' => 'developer',
            'repositories' => ['missing'],
        ], '/tmp/developer.yaml');

        $this->expectException(UnknownRepositoryException::class);
        $this->expectExceptionMessage('Unknown repository `missing`. Available repositories: `example-org-api`, `openaction-codex-agentag`.');

        $this->resolver()->repositoriesFor($workflow);
    }

    private function resolver(): RepositoryResolver
    {
        return new RepositoryResolver(new AgentTagSettings(
            '@Codex',
            '/tmp/workspace',
            '/tmp/workspace/workflows',
            'git@github.com:openaction-codex/agentag.git,git@github.com:example-org/api.git',
        ));
    }
}
