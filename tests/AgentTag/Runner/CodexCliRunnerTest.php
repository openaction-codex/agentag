<?php

namespace App\Tests\AgentTag\Runner;

use App\AgentTag\Runner\AgentRunnerInput;
use App\AgentTag\Runner\CodexCliRunner;
use App\AgentTag\Runner\ProcessFactory;
use App\AgentTag\Runner\RunnerProcess;
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

    #[\Override]
    public function create(array $command, string $workingDirectory, array $environment, string $input, int $timeoutSeconds): RunnerProcess
    {
        $this->command = $command;
        $this->workingDirectory = $workingDirectory;
        $this->environment = $environment;
        $this->input = $input;
        $this->timeoutSeconds = $timeoutSeconds;

        return new FakeRunnerProcess($command);
    }
}

final readonly class FakeRunnerProcess implements RunnerProcess
{
    /**
     * @param list<string> $command
     */
    public function __construct(private array $command)
    {
    }

    #[\Override]
    public function run(): int
    {
        $outputPathIndex = array_search('--output-last-message', $this->command, true);
        if (is_int($outputPathIndex)) {
            file_put_contents($this->command[$outputPathIndex + 1], 'Final answer from Codex.');
        }

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
        return '{"usage":{"input_tokens":12,"output_tokens":8}}';
    }

    #[\Override]
    public function errorOutput(): string
    {
        return '';
    }
}
