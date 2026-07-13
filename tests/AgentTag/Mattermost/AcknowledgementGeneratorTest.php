<?php

namespace App\Tests\AgentTag\Mattermost;

use App\AgentTag\Configuration\AgentTagSettings;
use App\AgentTag\Mattermost\AcknowledgementGenerator;
use App\AgentTag\Runner\ProcessFactory;
use App\AgentTag\Runner\RunnerProcess;
use PHPUnit\Framework\TestCase;

final class AcknowledgementGeneratorTest extends TestCase
{
    public function testItUsesTheCheapLowReasoningModelAndKeepsTheUsersLanguage(): void
    {
        $factory = new AcknowledgementProcessFactory('{"title":"Corriger les tests de facturation","acknowledgement":"Espace prêt. Je reproduis les échecs de facturation.","route":"sol-xhigh","selection_reason":"Bug de complexité moyenne dont la cause reste à identifier."}');
        $generator = new AcknowledgementGenerator($factory, new AgentTagSettings('@Codex', '/tmp', acknowledgementModel: 'gpt-5.6-luna'));

        $presentation = $generator->generate('@Codex corrige les tests de facturation', '/tmp');

        self::assertSame('Corriger les tests de facturation', $presentation->title);
        self::assertSame('Espace prêt. Je reproduis les échecs de facturation.', $presentation->acknowledgement);
        self::assertSame('sol-xhigh', $presentation->modelSelection->route);
        self::assertSame('gpt-5.6-sol', $presentation->modelSelection->model);
        self::assertSame('xhigh', $presentation->modelSelection->effort);
        self::assertSame('Bug de complexité moyenne dont la cause reste à identifier.', $presentation->modelSelection->reason);
        self::assertContains('gpt-5.6-luna', $factory->command);
        self::assertContains('model_reasoning_effort="low"', $factory->command);
        self::assertContains('--ephemeral', $factory->command);
        self::assertStringContainsString('sol-xhigh', $factory->input);
        self::assertStringNotContainsString('sol-high', $factory->input);
        self::assertStringNotContainsString('sol-max', $factory->input);
        self::assertStringContainsString('terra-max', $factory->input);
    }

    public function testItSafelyUsesLunaMaxWhenTheGeneratedRouteIsInvalid(): void
    {
        $factory = new AcknowledgementProcessFactory('{"title":"Inspect request","acknowledgement":"Workspace ready. I am inspecting it.","route":"unknown","selection_reason":"Unclear."}');
        $generator = new AcknowledgementGenerator($factory, new AgentTagSettings('@Codex', '/tmp'));

        $presentation = $generator->generate('inspect this', '/tmp');

        self::assertSame('luna-max', $presentation->modelSelection->route);
        self::assertSame('max', $presentation->modelSelection->effort);
        self::assertSame('main', $presentation->modelSelection->agent);
    }
}

final class AcknowledgementProcessFactory implements ProcessFactory
{
    /** @var list<string> */
    public array $command = [];

    public string $input = '';

    public function __construct(private readonly string $response)
    {
    }

    #[\Override]
    public function create(array $command, string $workingDirectory, array $environment, string $input, int $timeoutSeconds): RunnerProcess
    {
        $this->command = $command;
        $this->input = $input;

        return new AcknowledgementRunnerProcess($command, $this->response);
    }
}

final class AcknowledgementRunnerProcess implements RunnerProcess
{
    /** @param list<string> $command */
    public function __construct(private readonly array $command, private readonly string $response)
    {
    }

    #[\Override]
    public function run(?callable $callback = null): int
    {
        return 0;
    }

    #[\Override]
    public function start(?callable $callback = null): void
    {
        $index = array_search('--output-last-message', $this->command, true);
        if (is_int($index)) {
            file_put_contents($this->command[$index + 1], $this->response);
        }
    }

    #[\Override]
    public function wait(?callable $callback = null): int
    {
        return 0;
    }

    #[\Override]
    public function isRunning(): bool
    {
        return false;
    }

    #[\Override]
    public function stop(float $timeout = 10.0): int
    {
        return 0;
    }

    #[\Override]
    public function exitCode(): int
    {
        return 0;
    }

    #[\Override]
    public function output(): string
    {
        return '';
    }

    #[\Override]
    public function errorOutput(): string
    {
        return '';
    }
}
