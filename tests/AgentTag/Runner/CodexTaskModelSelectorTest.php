<?php

namespace App\Tests\AgentTag\Runner;

use App\AgentTag\Configuration\AgentTagSettings;
use App\AgentTag\Runner\CodexTaskModelSelector;
use App\AgentTag\Runner\ProcessFactory;
use App\AgentTag\Runner\RunnerProcess;
use PHPUnit\Framework\TestCase;

final class CodexTaskModelSelectorTest extends TestCase
{
    public function testItUsesEphemeralLunaHighAndAConstrainedOutputSchema(): void
    {
        $factory = new ModelSelectionProcessFactory('{"route":"terra-high","selection_reason":"Precise, verifiable bug fix."}');
        $selector = new CodexTaskModelSelector($factory, new AgentTagSettings('@Codex', '/tmp', modelSelectionModel: 'gpt-5.6-luna'));

        $selection = $selector->select('@Codex implement the billing fix');

        self::assertSame('terra-high', $selection->route);
        self::assertSame('gpt-5.6-terra', $selection->model);
        self::assertSame('high', $selection->effort);
        self::assertSame('Precise, verifiable bug fix.', $selection->reason);
        self::assertContains('gpt-5.6-luna', $factory->command);
        self::assertContains('model_reasoning_effort="high"', $factory->command);
        self::assertContains('--ephemeral', $factory->command);
        self::assertContains('--output-schema', $factory->command);
        $schema = json_decode($factory->schema, true, flags: \JSON_THROW_ON_ERROR);
        self::assertIsArray($schema);
        $properties = $schema['properties'] ?? null;
        self::assertIsArray($properties);
        $route = $properties['route'] ?? null;
        self::assertIsArray($route);
        self::assertSame([
            'luna-high',
            'luna-max',
            'terra-medium',
            'terra-high',
            'terra-xhigh',
            'terra-max',
            'sol-medium',
            'sol-xhigh',
        ], $route['enum'] ?? null);
        self::assertStringContainsString('Minimize quota usage while preserving correctness, judgment, and completeness.', $factory->input);
        self::assertStringContainsString('Honor an explicit request for a model or route.', $factory->input);
        self::assertStringContainsString('health/model/skills check, or simple confirmation: luna-high.', $factory->input);
        self::assertStringContainsString('Genuinely simple or deterministic work, including linear status, assignment, labels, comments, or writing: luna-high.', $factory->input);
        self::assertStringContainsString('Always use luna-max, and no other route, for OpenAction MCP manipulation or information retrieval.', $factory->input);
        self::assertStringContainsString('Default for routine agentic, product behavior questions, multi-step tool work, and functional testing: luna-max.', $factory->input);
        self::assertStringContainsString('Default for coding tasks, including specification writing, implementation, PR reviews, and technical diagnostics/debugging: terra-max.', $factory->input);
        self::assertStringContainsString('Security-sensitive, architectural, high-blast-radius, highly ambiguous, uncertain, or exceptionally difficult/complex work: sol-xhigh.', $factory->input);
        self::assertStringContainsString('Multiple files, tool calls, MCP calls, and arithmetic do not alone justify escalation.', $factory->input);
        self::assertStringContainsString('Escalate from Luna only when the work meets a Terra or Sol condition above;', $factory->input);
    }

    public function testItSupportsEveryRoutingProfile(): void
    {
        $profiles = [
            'luna-high' => ['gpt-5.6-luna', 'high'],
            'luna-max' => ['gpt-5.6-luna', 'max'],
            'terra-medium' => ['gpt-5.6-terra', 'medium'],
            'terra-high' => ['gpt-5.6-terra', 'high'],
            'terra-xhigh' => ['gpt-5.6-terra', 'xhigh'],
            'terra-max' => ['gpt-5.6-terra', 'max'],
            'sol-medium' => ['gpt-5.6-sol', 'medium'],
            'sol-xhigh' => ['gpt-5.6-sol', 'xhigh'],
        ];

        foreach ($profiles as $route => [$model, $effort]) {
            $factory = new ModelSelectionProcessFactory(json_encode([
                'route' => $route,
                'selection_reason' => 'Test selection.',
            ], \JSON_THROW_ON_ERROR));
            $selector = new CodexTaskModelSelector($factory, new AgentTagSettings('@Codex', '/tmp'));

            $selection = $selector->select('route this request');

            self::assertSame($route, $selection->route);
            self::assertSame($model, $selection->model);
            self::assertSame($effort, $selection->effort);
        }
    }

    public function testItSupportsLunaHighForExtremelySimpleTasks(): void
    {
        $factory = new ModelSelectionProcessFactory('{"route":"luna-high","selection_reason":"Simple agent status check."}');
        $selector = new CodexTaskModelSelector($factory, new AgentTagSettings('@Codex', '/tmp'));

        $selection = $selector->select('check the current agent status');

        self::assertSame('luna-high', $selection->route);
        self::assertSame('gpt-5.6-luna', $selection->model);
        self::assertSame('high', $selection->effort);
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
