<?php

namespace App\Tests\AgentTag\Developer;

use App\AgentTag\Codebase\RepositoryClone;
use App\AgentTag\Configuration\ConfiguredRepository;
use App\AgentTag\Developer\ImplementationPromptBuilder;
use App\AgentTag\Developer\ImplementationRunInput;
use App\AgentTag\Session\ChatThreadContext;
use App\AgentTag\Session\ChatThreadMessage;
use App\AgentTag\Workflow\WorkflowDefinition;
use PHPUnit\Framework\TestCase;

final class ImplementationPromptBuilderTest extends TestCase
{
    public function testItBuildsImplementationPromptForAllowedRepository(): void
    {
        $repository = ConfiguredRepository::fromSshUrl('git@github.com:openaction-codex/agentag.git');
        $workflow = WorkflowDefinition::fromArray([
            'name' => 'developer',
            'repositories' => ['openaction-codex-agentag'],
            'runner_mode' => 'codex-full-access',
        ], '/tmp/developer.yaml');
        $input = new ImplementationRunInput(
            'Technical spec body',
            new RepositoryClone($repository, '/tmp/run/codebase/openaction-codex-agentag'),
            'agentag/ope-123',
            ['composer check'],
            new ChatThreadContext([
                new ChatThreadMessage('root', 'alice', 'Please implement this.'),
            ]),
        );

        $prompt = (new ImplementationPromptBuilder())->build($workflow, $input);

        self::assertStringContainsString('Technical spec body', $prompt);
        self::assertStringContainsString('/tmp/run/codebase/openaction-codex-agentag', $prompt);
        self::assertStringContainsString('Use or create branch: agentag/ope-123', $prompt);
        self::assertStringContainsString('composer check', $prompt);
        self::assertStringContainsString('Changed files, test results, artifacts', $prompt);
        self::assertStringContainsString('Please implement this.', $prompt);
    }

    public function testItRejectsRepositoriesNotAllowedByWorkflow(): void
    {
        $workflow = WorkflowDefinition::fromArray([
            'name' => 'developer',
            'repositories' => ['other-repo'],
        ], '/tmp/developer.yaml');
        $input = new ImplementationRunInput(
            'Technical spec body',
            new RepositoryClone(ConfiguredRepository::fromSshUrl('git@github.com:openaction-codex/agentag.git'), '/tmp/repo'),
            'agentag/ope-123',
            [],
        );

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('not allowed to implement repository');

        (new ImplementationPromptBuilder())->build($workflow, $input);
    }
}
