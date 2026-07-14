<?php

namespace App\Tests\AgentTag\Runner;

use App\AgentTag\Configuration\AgentTagSettings;
use App\AgentTag\Runner\CodexTaskModelSelector;
use App\AgentTag\Runner\ProcessFactory;
use App\AgentTag\Runner\RunnerProcess;
use PHPUnit\Framework\TestCase;

final class CodexTaskModelSelectorTest extends TestCase
{
    public function testItUsesEphemeralLunaMediumAndAConstrainedOutputSchema(): void
    {
        $factory = new ModelSelectionProcessFactory('{"route":"sol-xhigh","selection_reason":"Coding task requiring implementation."}');
        $selector = new CodexTaskModelSelector($factory, new AgentTagSettings('@Codex', '/tmp', modelSelectionModel: 'gpt-5.6-luna'));

        $selection = $selector->select('@Codex implement the billing fix');

        self::assertSame('sol-xhigh', $selection->route);
        self::assertSame('gpt-5.6-sol', $selection->model);
        self::assertSame('xhigh', $selection->effort);
        self::assertSame('Coding task requiring implementation.', $selection->reason);
        self::assertContains('gpt-5.6-luna', $factory->command);
        self::assertContains('model_reasoning_effort="medium"', $factory->command);
        self::assertContains('--ephemeral', $factory->command);
        self::assertContains('--output-schema', $factory->command);
        $schema = json_decode($factory->schema, true, flags: \JSON_THROW_ON_ERROR);
        self::assertIsArray($schema);
        $properties = $schema['properties'] ?? null;
        self::assertIsArray($properties);
        $route = $properties['route'] ?? null;
        self::assertIsArray($route);
        self::assertSame(['luna-max', 'sol-medium', 'sol-xhigh'], $route['enum'] ?? null);
        self::assertStringContainsString('every coding task', $factory->input);
        self::assertStringContainsString('simple questions about the current implementation or product', $factory->input);
        self::assertStringContainsString('every other task', $factory->input);
    }

    public function testItUsesSolMediumWhenTheGeneratedRouteIsInvalid(): void
    {
        $factory = new ModelSelectionProcessFactory('{"route":"unknown","selection_reason":"Unclear."}');
        $selector = new CodexTaskModelSelector($factory, new AgentTagSettings('@Codex', '/tmp'));

        $selection = $selector->select('inspect this');

        self::assertSame('sol-medium', $selection->route);
        self::assertSame('gpt-5.6-sol', $selection->model);
        self::assertSame('medium', $selection->effort);
    }

    public function testItUsesSolMediumWhenTheClassifierOutputIsNotJson(): void
    {
        $selector = new CodexTaskModelSelector(new ModelSelectionProcessFactory('not-json'), new AgentTagSettings('@Codex', '/tmp'));

        $selection = $selector->select('@Codex haz algo');

        self::assertSame('sol-medium', $selection->route);
        self::assertSame('The model selector was unavailable, so the general-purpose route was used.', $selection->reason);
    }
}

final class ModelSelectionProcessFactory implements ProcessFactory
{
    /** @var list<string> */
    public array $command = [];

    public string $schema = '';

    public string $input = '';

    public function __construct(private readonly string $response)
    {
    }

    #[\Override]
    public function create(array $command, string $workingDirectory, array $environment, string $input, int $timeoutSeconds): RunnerProcess
    {
        $this->command = $command;
        $this->input = $input;
        $schemaIndex = array_search('--output-schema', $command, true);
        if (is_int($schemaIndex)) {
            $this->schema = (string) file_get_contents($command[$schemaIndex + 1]);
        }

        return new ModelSelectionRunnerProcess($command, $this->response);
    }
}

final class ModelSelectionRunnerProcess implements RunnerProcess
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
