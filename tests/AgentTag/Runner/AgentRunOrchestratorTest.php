<?php

namespace App\Tests\AgentTag\Runner;

use App\AgentTag\Run\RunEventRecorder;
use App\AgentTag\Runner\AgentArtifact;
use App\AgentTag\Runner\AgentRunnerInput;
use App\AgentTag\Runner\AgentRunnerInterface;
use App\AgentTag\Runner\AgentRunnerResult;
use App\AgentTag\Runner\AgentRunOrchestrator;
use App\AgentTag\Runner\TokenUsage;
use App\AgentTag\Security\SensitiveTextRedactor;
use App\AgentTag\Workspace\WorkspaceLayout;
use App\Entity\AgentRun;
use App\Entity\ChatSession;
use App\Entity\RunEvent;
use App\Tests\RefreshDatabaseTrait;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class AgentRunOrchestratorTest extends KernelTestCase
{
    use RefreshDatabaseTrait;

    private string $workspaceDirectory;

    #[\Override]
    protected function setUp(): void
    {
        self::bootKernel();
        $this->refreshDatabase();
        $this->workspaceDirectory = sys_get_temp_dir().'/agentag-orchestrator-'.bin2hex(random_bytes(6));
    }

    #[\Override]
    protected function tearDown(): void
    {
        $this->removeDirectory($this->workspaceDirectory);
    }

    public function testItRunsThroughTheInterfaceAndPersistsResultMetadata(): void
    {
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $entityManager);

        $session = new ChatSession('mattermost:team:channel:root', 'mattermost', 'team', 'channel', 'root', new \DateTimeImmutable());
        $run = new AgentRun($session, 'accepted', new \DateTimeImmutable(), 'input');
        $entityManager->persist($session);
        $entityManager->persist($run);
        $entityManager->flush();

        $fakeRunner = new FakeAgentRunner();
        $orchestrator = new AgentRunOrchestrator(
            $fakeRunner,
            new WorkspaceLayout($this->workspaceDirectory, $this->workspaceDirectory.'/workflows'),
            new SensitiveTextRedactor(),
            $entityManager,
            new RunEventRecorder($entityManager, new SensitiveTextRedactor(), new NullLogger()),
        );

        $orchestrator->run($run, 'run-123', 'Prompt with token=secret', 'codex-full-access', 120, ['A' => 'B']);

        self::assertSame('completed', $run->status());
        self::assertSame('done with token=[REDACTED]', $run->outputSummary());
        self::assertStringContainsString('stdout: stdout token=[REDACTED]', (string) $run->logSummary());
        self::assertSame($this->workspaceDirectory.'/runs/run-123', $run->workspacePath());
        self::assertSame(['/tmp/artifact.txt'], $run->artifacts());
        self::assertSame(0, $run->exitCode());
        self::assertSame(10, $run->inputTokens());
        self::assertSame(5, $run->outputTokens());
        self::assertSame(15, $run->totalTokens());
        $events = $entityManager->getRepository(RunEvent::class)->findBy(['run' => $run], ['id' => 'ASC']);
        self::assertCount(4, $events);
        self::assertSame(RunEvent::TYPE_WORKSPACE_PREPARED, $events[0]->type());
        self::assertSame(RunEvent::TYPE_RUNNER_STARTED, $events[1]->type());
        self::assertSame(RunEvent::TYPE_RUNNER_FINISHED, $events[2]->type());
        self::assertSame(RunEvent::TYPE_TOKEN_USAGE, $events[3]->type());
        self::assertSame(15, $events[3]->metadata()['total_tokens']);
        $sessionId = $session->id();
        self::assertNotNull($sessionId);
        $entityManager->clear();
        $storedSession = $entityManager->getRepository(ChatSession::class)->find($sessionId);
        self::assertInstanceOf(ChatSession::class, $storedSession);
        self::assertSame(10, $storedSession->inputTokens());
        self::assertSame(5, $storedSession->outputTokens());
        self::assertSame(15, $storedSession->totalTokens());
        self::assertNotNull($fakeRunner->input);
        self::assertSame($this->workspaceDirectory.'/runs/run-123', $fakeRunner->input->workingDirectory());
        self::assertSame($this->workspaceDirectory.'/artifacts/run-123', $fakeRunner->input->artifactsDirectory());
    }

    private function removeDirectory(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        foreach (array_reverse(glob($path.'/{,.}*', \GLOB_BRACE) ?: []) as $file) {
            if ('.' === basename($file) || '..' === basename($file)) {
                continue;
            }

            is_dir($file) ? rmdir($file) : unlink($file);
        }

        rmdir($path);
    }
}

final class FakeAgentRunner implements AgentRunnerInterface
{
    public ?AgentRunnerInput $input = null;

    #[\Override]
    public function run(AgentRunnerInput $input): AgentRunnerResult
    {
        $this->input = $input;

        return new AgentRunnerResult(
            0,
            'done with token=secret',
            'stdout token=secret',
            '',
            [new AgentArtifact('/tmp/artifact.txt', 'artifact')],
            new TokenUsage(10, 5),
        );
    }
}
