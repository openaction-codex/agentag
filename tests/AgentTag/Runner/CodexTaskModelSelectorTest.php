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
        self::assertSame(['luna-low', 'luna-max', 'sol-medium', 'sol-xhigh'], $route['enum'] ?? null);
        self::assertStringContainsString("stop, cancel, or interrupt the agent's current work", $factory->input);
        self::assertStringContainsString('current agent status or current MCP server status', $factory->input);
        self::assertStringContainsString('functional testing of a PR (PR functional validation)', $factory->input);
        self::assertStringContainsString('writing a technical specification', $factory->input);
        self::assertStringContainsString('every remaining coding task', $factory->input);
        self::assertStringContainsString('every remaining simple, routine task that does not require long context', $factory->input);
        self::assertStringContainsString('product questions, implementation questions, general questions, classification, extraction, short summarization, status updates, and other simple MCP reads', $factory->input);
        self::assertStringContainsString('every remaining non-coding task', $factory->input);
        self::assertStringContainsString('long context, broad synthesis, deeper judgment, or consequential recommendations', $factory->input);
    }

    public function testItSupportsLunaLowForExtremelySimpleTasks(): void
    {
        $factory = new ModelSelectionProcessFactory('{"route":"luna-low","selection_reason":"Simple agent status check."}');
        $selector = new CodexTaskModelSelector($factory, new AgentTagSettings('@Codex', '/tmp'));

        $selection = $selector->select('check the current agent status');

        self::assertSame('luna-low', $selection->route);
        self::assertSame('gpt-5.6-luna', $selection->model);
        self::assertSame('low', $selection->effort);
        self::assertSame('Simple agent status check.', $selection->reason);
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
