<?php

namespace App\Tests\AgentTag\Runner;

use App\AgentTag\Runner\AgentRunnerInput;
use App\AgentTag\Runner\AgentRunnerProgress;
use App\AgentTag\Runner\AgentRunnerProgressSink;
use App\AgentTag\Runner\CodexCliRunner;
use App\AgentTag\Runner\ProcessFactory;
use App\AgentTag\Runner\RunnerProcess;
use App\AgentTag\Runner\SubagentSessionInspector;
use App\AgentTag\Runner\SubagentSessionMetadata;
use PHPUnit\Framework\TestCase;

final class CodexCliRunnerTest extends TestCase
{
    private string $workingDirectory;

    private string $artifactsDirectory;

    #[\Override]
    protected function setUp(): void
    {
        $this->workingDirectory = sys_get_temp_dir().'/agentag-codex-runner-'.bin2hex(random_bytes(6));
        $this->artifactsDirectory = $this->workingDirectory.'/artifacts';
        mkdir($this->workingDirectory);
    }

    #[\Override]
    protected function tearDown(): void
    {
        foreach (glob($this->artifactsDirectory.'/*') ?: [] as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }

        if (is_dir($this->artifactsDirectory)) {
            rmdir($this->artifactsDirectory);
        }

        if (is_dir($this->workingDirectory)) {
            rmdir($this->workingDirectory);
        }
    }

    public function testItRunsCodexExecInFullAccessMode(): void
    {
        $factory = new TraceableProcessFactory();
        $runner = new CodexCliRunner($factory);

        $result = $runner->run(new AgentRunnerInput(
            'Implement the task.',
            $this->workingDirectory,
            $this->artifactsDirectory,
            ['CODEX_HOME' => '/tmp/codex-home'],
            300,
            'codex-full-access',
        ));

        self::assertSame([
            'codex',
            'exec',
            '--dangerously-bypass-approvals-and-sandbox',
            '--skip-git-repo-check',
            '--json',
            '--model',
            'gpt-5.6-luna',
            '-c',
            'model_reasoning_effort="max"',
            '--cd',
            $this->workingDirectory,
            '--output-last-message',
            $this->artifactsDirectory.'/codex-last-message.txt',
            '-',
        ], $factory->command);
        self::assertSame($this->workingDirectory, $factory->workingDirectory);
        self::assertSame(['CODEX_HOME' => '/tmp/codex-home'], $factory->environment);
        self::assertSame('Implement the task.', $factory->input);
        self::assertSame(300, $factory->timeoutSeconds);
        self::assertTrue($result->successful());
        self::assertSame('Final answer from Codex.', $result->finalMessage());
        $tokenUsage = $result->tokenUsage();
        self::assertNotNull($tokenUsage);
        self::assertSame(12, $tokenUsage->inputTokens());
        self::assertSame(8, $tokenUsage->outputTokens());
    }

    public function testItFallsBackToTheLastAgentMessageWhenTheLastMessageFileIsMissing(): void
    {
        $factory = new TraceableProcessFactory();
        $factory->writeLastMessage = false;
        $factory->stdout = implode("\n", [
            '{"type":"thread.started","thread_id":"thread-id"}',
            '{"type":"item.completed","item":{"id":"item_0","type":"agent_message","text":"I am checking that now."}}',
            '{"type":"item.completed","item":{"id":"item_1","type":"command_execution","aggregated_output":"AGENTS.md\n","exit_code":0}}',
            '{"type":"item.completed","item":{"id":"item_2","type":"agent_message","text":"Final answer from JSONL."}}',
            '{"usage":{"input_tokens":12,"output_tokens":8}}',
        ]);
        $runner = new CodexCliRunner($factory);

        $result = $runner->run(new AgentRunnerInput(
            'Implement the task.',
            $this->workingDirectory,
            $this->artifactsDirectory,
            [],
            300,
            'codex-full-access',
        ));

        self::assertTrue($result->successful());
        self::assertSame('Final answer from JSONL.', $result->finalMessage());
        self::assertSame('thread-id', $result->sessionId());
    }

    public function testItDoesNotExposeJsonEventsWhenCodexProducesNoAgentMessage(): void
    {
        $factory = new TraceableProcessFactory();
        $factory->writeLastMessage = false;
        $factory->stdout = implode("\n", [
            '{"type":"thread.started","thread_id":"thread-id"}',
            '{"type":"turn.started"}',
            '{"usage":{"input_tokens":12,"output_tokens":8}}',
        ]);
        $runner = new CodexCliRunner($factory);

        $result = $runner->run(new AgentRunnerInput(
            'Implement the task.',
            $this->workingDirectory,
            $this->artifactsDirectory,
            [],
            300,
            'codex-full-access',
        ));

        self::assertSame('Run completed, but Codex did not provide a final message.', $result->finalMessage());
    }

    public function testItStopsCodexWhenInterruptionIsRequested(): void
    {
        $factory = new TraceableProcessFactory();
        $runner = new CodexCliRunner($factory);

        $result = $runner->run(new AgentRunnerInput(
            'Implement the task.',
            $this->workingDirectory,
            $this->artifactsDirectory,
            [],
            300,
            'codex-full-access',
            interruptionChecker: static fn (): bool => true,
        ));

        self::assertSame(130, $result->exitCode());
        self::assertSame('Run interrupted by a newer message in this thread.', $result->finalMessage());
        self::assertNotNull($factory->process);
        self::assertTrue($factory->process->stopped);
    }

    public function testItPersistsTheCodexThreadAndResumesTheSameSession(): void
    {
        $factory = new TraceableProcessFactory();
        $factory->callbackOutput = "{\"type\":\"thread.started\",\"thread_id\":\"019abc-session\"}\n";
        $started = [];
        $runner = new CodexCliRunner($factory);

        $result = $runner->run(new AgentRunnerInput(
            'Continue the task.',
            $this->workingDirectory,
            $this->artifactsDirectory,
            [],
            300,
            'codex-full-access',
            resumeSessionId: 'prior-session',
            sessionStartedCallback: static function (string $sessionId) use (&$started): void { $started[] = $sessionId; },
        ));

        self::assertSame([
            'codex', 'exec', 'resume',
            '--dangerously-bypass-approvals-and-sandbox',
            '--skip-git-repo-check',
            '--json',
            '--model', 'gpt-5.6-luna',
            '-c', 'model_reasoning_effort="max"',
            '--output-last-message', $this->artifactsDirectory.'/codex-last-message.txt',
            'prior-session',
            '-',
        ], $factory->command);
        self::assertSame(['019abc-session'], $started);
        self::assertSame('019abc-session', $result->sessionId());
    }

    public function testItCanPinADifferentParentModelAndReasoningEffort(): void
    {
        $factory = new TraceableProcessFactory();
        $runner = new CodexCliRunner($factory, 'gpt-5.6-sol', 'high');

        $runner->run(new AgentRunnerInput(
            'Handle the difficult task.',
            $this->workingDirectory,
            $this->artifactsDirectory,
            [],
            300,
            'codex-full-access',
        ));

        self::assertContains('gpt-5.6-sol', $factory->command);
        self::assertContains('model_reasoning_effort="high"', $factory->command);
    }

    public function testItReportsVerifiedMetadataWhenCodexStartsASubagent(): void
    {
        $factory = new TraceableProcessFactory();
        $factory->callbackOutput = <<<'JSON'
{"type":"item.completed","item":{"id":"item_2","type":"collab_tool_call","tool":"spawn_agent","sender_thread_id":"parent","receiver_thread_ids":["019f5b58-902e-7132-9185-049c23e5cc7b"],"prompt":"Implement it","agents_states":{},"status":"completed"}}

JSON;
        $inspector = new TraceableSubagentSessionInspector();
        $sink = new TraceableAgentRunnerProgressSink();
        $runner = new CodexCliRunner($factory, subagentSessionInspector: $inspector);

        $runner->run(new AgentRunnerInput(
            'Implement the advanced task.',
            $this->workingDirectory,
            $this->artifactsDirectory,
            ['CODEX_HOME' => '/tmp/codex-home'],
            300,
            'codex-full-access',
            progressSink: $sink,
        ));

        self::assertSame('/tmp/codex-home', $inspector->codexHome);
        self::assertCount(1, $sink->progress);
        self::assertSame('subagent_started', $sink->progress[0]->type());
        self::assertSame([
            'thread_id' => '019f5b58-902e-7132-9185-049c23e5cc7b',
            'agent' => 'sol-xhigh',
            'model' => 'gpt-5.6-sol',
            'reasoning_effort' => 'xhigh',
            'verified' => true,
        ], $sink->progress[0]->context());
    }

    public function testItExtractsAWaitDirectiveWithoutShowingItToMattermost(): void
    {
        $factory = new TraceableProcessFactory();
        $factory->lastMessage = "PR #184 is open and CI is running.\n\n<!-- agentag:{\"action\":\"wait\",\"seconds\":300,\"reason\":\"Waiting for CI\"} -->";

        $result = (new CodexCliRunner($factory))->run(new AgentRunnerInput(
            'Fix this and watch CI.',
            $this->workingDirectory,
            $this->artifactsDirectory,
            [],
            300,
            'codex-full-access',
        ));

        self::assertSame('PR #184 is open and CI is running.', $result->finalMessage());
        self::assertNotNull($result->continuation());
        self::assertSame(300, $result->continuation()->delaySeconds());
        self::assertSame('Waiting for CI', $result->continuation()->reason());
    }
}

final class TraceableSubagentSessionInspector implements SubagentSessionInspector
{
    public ?string $codexHome = null;

    #[\Override]
    public function inspect(string $threadId, string $codexHome): SubagentSessionMetadata
    {
        $this->codexHome = $codexHome;

        return new SubagentSessionMetadata($threadId, 'sol-xhigh', 'gpt-5.6-sol', 'xhigh');
    }
}

final class TraceableAgentRunnerProgressSink implements AgentRunnerProgressSink
{
    /** @var list<AgentRunnerProgress> */
    public array $progress = [];

    #[\Override]
    public function onProgress(AgentRunnerProgress $progress): void
    {
        $this->progress[] = $progress;
    }

    #[\Override]
    public function onHeartbeat(): void
    {
    }
}

final class TraceableProcessFactory implements ProcessFactory
{
    /**
     * @var list<string>
     */
    public array $command = [];

    public string $workingDirectory = '';

    /**
     * @var array<string, string>
     */
    public array $environment = [];

    public string $input = '';

    public int $timeoutSeconds = 0;

    public ?FakeRunnerProcess $process = null;

    public bool $writeLastMessage = true;

    public string $lastMessage = 'Final answer from Codex.';

    public string $stdout = '{"usage":{"input_tokens":12,"output_tokens":8}}';

    public string $callbackOutput = "{\"type\":\"agent_message\",\"message\":\"Working on it.\"}\n";

    #[\Override]
    public function create(array $command, string $workingDirectory, array $environment, string $input, int $timeoutSeconds): RunnerProcess
    {
        $this->command = $command;
        $this->workingDirectory = $workingDirectory;
        $this->environment = $environment;
        $this->input = $input;
        $this->timeoutSeconds = $timeoutSeconds;

        $this->process = new FakeRunnerProcess(
            $command,
            $this->writeLastMessage,
            $this->lastMessage,
            $this->stdout,
            $this->callbackOutput,
        );

        return $this->process;
    }
}

final class FakeRunnerProcess implements RunnerProcess
{
    public bool $stopped = false;

    private bool $running = false;

    private int $pollsRemaining = 0;

    /**
     * @param list<string> $command
     */
    public function __construct(
        private array $command,
        private bool $writeLastMessage,
        private string $lastMessage,
        private string $stdout,
        private string $callbackOutput,
    ) {
    }

    #[\Override]
    public function run(?callable $callback = null): int
    {
        $this->start($callback);
        $this->running = false;

        return 0;
    }

    #[\Override]
    public function start(?callable $callback = null): void
    {
        $this->running = true;
        $this->pollsRemaining = 1;
        $outputPathIndex = array_search('--output-last-message', $this->command, true);
        if ($this->writeLastMessage && is_int($outputPathIndex)) {
            file_put_contents($this->command[$outputPathIndex + 1], $this->lastMessage);
        }
        if (null !== $callback) {
            $callback('out', $this->callbackOutput);
        }
    }

    #[\Override]
    public function wait(?callable $callback = null): int
    {
        $this->running = false;

        return 0;
    }

    #[\Override]
    public function isRunning(): bool
    {
        if (!$this->running) {
            return false;
        }

        if ($this->pollsRemaining > 0) {
            --$this->pollsRemaining;

            return true;
        }

        $this->running = false;

        return false;
    }

    #[\Override]
    public function stop(float $timeout = 10.0): int
    {
        $this->stopped = true;
        $this->running = false;

        return 130;
    }

    #[\Override]
    public function exitCode(): int
    {
        return 0;
    }

    #[\Override]
    public function output(): string
    {
        return $this->stdout;
    }

    #[\Override]
    public function errorOutput(): string
    {
        return '';
    }
}
