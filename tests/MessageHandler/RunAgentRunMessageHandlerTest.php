<?php

namespace App\Tests\MessageHandler;

use App\AgentTag\Agent\AgentProfile;
use App\AgentTag\Agent\AgentProfileProvider;
use App\AgentTag\Mattermost\MattermostInboundEvent;
use App\AgentTag\Mattermost\MattermostNotifier;
use App\AgentTag\Mattermost\MattermostRunProgressSinkFactory;
use App\AgentTag\Mattermost\TaskCardRenderer;
use App\AgentTag\Run\AgentRunTurnGate;
use App\AgentTag\Run\RunEventRecorder;
use App\AgentTag\Runner\AgentRunnerInput;
use App\AgentTag\Runner\AgentRunnerInterface;
use App\AgentTag\Runner\AgentRunnerResult;
use App\AgentTag\Runner\AgentRunOrchestrator;
use App\AgentTag\Runner\AgentRunPromptBuilder;
use App\AgentTag\Runner\TaskContinuation;
use App\AgentTag\Runner\TaskModelSelection;
use App\AgentTag\Runner\TaskModelSelector;
use App\AgentTag\Security\SensitiveTextRedactor;
use App\AgentTag\Workspace\WorkspaceLayout;
use App\Entity\AgentRun;
use App\Entity\ChatSession;
use App\Entity\RunEvent;
use App\Message\PrepareAgentTaskMessage;
use App\Message\RunAgentRunMessage;
use App\MessageHandler\PrepareAgentTaskMessageHandler;
use App\MessageHandler\RunAgentRunMessageHandler;
use App\Tests\RefreshDatabaseTrait;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DelayStamp;

#[CoversClass(RunAgentRunMessageHandler::class)]
final class RunAgentRunMessageHandlerTest extends KernelTestCase
{
    use RefreshDatabaseTrait;

    private string $workspace;
    private string $root;

    #[\Override]
    protected function setUp(): void
    {
        self::bootKernel();
        $this->refreshDatabase();
        $this->root = sys_get_temp_dir().'/agentag-durable-'.bin2hex(random_bytes(5));
        $this->workspace = $this->root.'/workspace';
        mkdir($this->workspace, 0777, true);
    }

    #[\Override]
    protected function tearDown(): void
    {
        if (is_dir($this->root)) {
            (new \Symfony\Component\Filesystem\Filesystem())->remove($this->root);
        }
    }

    public function testSuccessfulStageCanWaitAndResumeWithTheSameCodexSession(): void
    {
        $entityManager = $this->entityManager();
        $run = $this->persistRun($entityManager);
        $bus = new DelayedTraceableMessageBus();
        $runner = new ContinuationAgentRunner(new AgentRunnerResult(
            0,
            'PR #184 opened. CI is running.',
            '',
            '',
            [],
            null,
            'codex-session-uuid',
            new TaskContinuation(300, 'Waiting for CI'),
        ));
        $recorder = new RunEventRecorder($entityManager, new SensitiveTextRedactor());
        $renderer = static::getContainer()->get(TaskCardRenderer::class);
        self::assertInstanceOf(TaskCardRenderer::class, $renderer);
        $notifier = new DurableTraceableNotifier();
        $handler = new RunAgentRunMessageHandler(
            $entityManager,
            new DurableAgentProfileProvider($this->workspace),
            new AgentRunPromptBuilder(),
            new AgentRunOrchestrator($runner, new WorkspaceLayout($this->workspace), new SensitiveTextRedactor(), $entityManager, $recorder),
            new MattermostRunProgressSinkFactory($notifier, $renderer, $entityManager, $recorder),
            new AgentRunTurnGate($entityManager),
            $bus,
        );

        $handler(new RunAgentRunMessage((int) $run->id()));

        self::assertSame(AgentRun::STATUS_WAITING, $run->status());
        self::assertSame('codex-session-uuid', $run->codexThreadId());
        self::assertSame('Waiting for CI', $run->waitReason());
        self::assertCount(1, $bus->delays);
        self::assertGreaterThanOrEqual(299000, $bus->delays[0]);
        self::assertStringContainsString('I’ll check again', $notifier->threadMessages[0]);
        $events = $entityManager->getRepository(RunEvent::class)->findBy(['run' => $run]);
        self::assertContains(RunEvent::TYPE_TASK_WAITING, array_map(static fn (RunEvent $event): string => $event->type(), $events));
    }

    public function testHighPriorityPreparationSelectsTheModelAndUpdatesTheExistingCard(): void
    {
        $entityManager = $this->entityManager();
        $run = $this->persistRun($entityManager);
        $renderer = static::getContainer()->get(TaskCardRenderer::class);
        self::assertInstanceOf(TaskCardRenderer::class, $renderer);
        $notifier = new DurableTraceableNotifier();
        $bus = new DelayedTraceableMessageBus();
        $factory = new MattermostRunProgressSinkFactory($notifier, $renderer, $entityManager);
        $handler = new PrepareAgentTaskMessageHandler(
            $entityManager,
            new DurableTaskModelSelector(),
            $renderer,
            $notifier,
            $factory,
            $bus,
        );

        $handler(new PrepareAgentTaskMessage((int) $run->id()));

        self::assertSame('Fix and watch CI', $run->title());
        self::assertSame('Workspace ready', $run->acknowledgement());
        self::assertSame('sol-xhigh', $run->modelSelection()->route);
        self::assertSame('sol-xhigh', $run->session()->modelSelection()?->route);
        self::assertSame('task-post', $run->taskPostId());
        self::assertSame([], $notifier->createdMessages);
        self::assertCount(1, $notifier->updatedMessages);
        self::assertStringStartsWith('> ', $notifier->updatedMessages[0]);
        self::assertStringContainsString('Model selected. Starting the task.', $notifier->updatedMessages[0]);
        self::assertStringContainsString('Model: **GPT-5.6 Sol · xhigh**', $notifier->updatedMessages[0]);
        self::assertStringContainsString('Contained feature with several interacting changes.', $notifier->updatedMessages[0]);
        self::assertSame([(int) $run->id()], $bus->runIds);
    }

    public function testPreparationReusesAnExistingSessionModelWithoutSelectingAgain(): void
    {
        $entityManager = $this->entityManager();
        $run = $this->persistRun($entityManager);
        $selection = TaskModelSelection::fromRoute('sol-medium', 'Selected by the first request in this thread.')
            ?? throw new \LogicException('Expected a valid test model selection.');
        $run->session()->selectModel($selection);
        $entityManager->flush();
        $renderer = static::getContainer()->get(TaskCardRenderer::class);
        self::assertInstanceOf(TaskCardRenderer::class, $renderer);
        $bus = new DelayedTraceableMessageBus();
        $handler = new PrepareAgentTaskMessageHandler(
            $entityManager,
            new FailingTaskModelSelector(),
            $renderer,
            new DurableTraceableNotifier(),
            new MattermostRunProgressSinkFactory(new DurableTraceableNotifier(), $renderer, $entityManager),
            $bus,
        );

        $handler(new PrepareAgentTaskMessage((int) $run->id()));

        self::assertSame('sol-medium', $run->modelSelection()->route);
        self::assertSame([(int) $run->id()], $bus->runIds);
    }

    public function testRedeliveredRunningTaskRecoversWithCodexResume(): void
    {
        $entityManager = $this->entityManager();
        $run = $this->persistRun($entityManager);
        $run->recordCodexThread('preserved-session-uuid');
        $run->markRunning();
        $entityManager->flush();
        $runner = new ContinuationAgentRunner(new AgentRunnerResult(0, 'Recovered and completed.', '', '', [], null, 'preserved-session-uuid'));
        $renderer = static::getContainer()->get(TaskCardRenderer::class);
        self::assertInstanceOf(TaskCardRenderer::class, $renderer);
        $recorder = new RunEventRecorder($entityManager, new SensitiveTextRedactor());
        $handler = new RunAgentRunMessageHandler(
            $entityManager,
            new DurableAgentProfileProvider($this->workspace),
            new AgentRunPromptBuilder(),
            new AgentRunOrchestrator($runner, new WorkspaceLayout($this->workspace), new SensitiveTextRedactor(), $entityManager, $recorder),
            new MattermostRunProgressSinkFactory(new DurableTraceableNotifier(), $renderer, $entityManager, $recorder),
            new AgentRunTurnGate($entityManager),
            new DelayedTraceableMessageBus(),
        );

        $handler(new RunAgentRunMessage((int) $run->id()));

        self::assertNotNull($runner->input);
        self::assertSame('preserved-session-uuid', $runner->input->resumeSessionId());
        self::assertStringContainsString('worker process restarted', $runner->input->prompt());
        self::assertSame(AgentRun::STATUS_COMPLETED, $run->status());
    }

    private function persistRun(EntityManagerInterface $entityManager): AgentRun
    {
        $session = new ChatSession('mattermost:team:channel:thread', 'team', 'channel', 'thread', new \DateTimeImmutable(), $this->workspace);
        $run = new AgentRun($session, AgentRun::STATUS_ACCEPTED, new \DateTimeImmutable(), contextSnapshot: 'User requested a CI-watched fix.', requesterId: 'requester', workspacePath: $this->workspace);
        $run->initializeTask('Fix and watch CI', 'Workspace ready', 'thomas', new \DateTimeImmutable('+1 day'), 2, 60, 'milestones');
        $run->assignTaskPost('task-post');
        $entityManager->persist($session);
        $entityManager->persist($run);
        $entityManager->flush();

        return $run;
    }

    private function entityManager(): EntityManagerInterface
    {
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $entityManager);

        return $entityManager;
    }
}

final readonly class DurableAgentProfileProvider implements AgentProfileProvider
{
    public function __construct(private string $workspace)
    {
    }

    #[\Override]
    public function profile(): AgentProfile
    {
        return new AgentProfile('agent', $this->workspace, null, 'codex-full-access', 30);
    }
}

final class ContinuationAgentRunner implements AgentRunnerInterface
{
    public ?AgentRunnerInput $input = null;

    public function __construct(private readonly AgentRunnerResult $result)
    {
    }

    #[\Override]
    public function run(AgentRunnerInput $input): AgentRunnerResult
    {
        $this->input = $input;

        return $this->result;
    }
}

final class DelayedTraceableMessageBus implements MessageBusInterface
{
    /** @var list<int> */
    public array $delays = [];
    /** @var list<int> */
    public array $runIds = [];

    #[\Override]
    public function dispatch(object $message, array $stamps = []): Envelope
    {
        foreach ($stamps as $stamp) {
            if ($stamp instanceof DelayStamp) {
                $this->delays[] = $stamp->getDelay();
            }
        }
        if ($message instanceof RunAgentRunMessage) {
            $this->runIds[] = $message->runId();
        }

        return new Envelope($message, $stamps);
    }
}

final class DurableTraceableNotifier implements MattermostNotifier
{
    /** @var list<string> */
    public array $threadMessages = [];
    /** @var list<string> */
    public array $createdMessages = [];
    /** @var list<string> */
    public array $updatedMessages = [];

    #[\Override]
    public function showTyping(MattermostInboundEvent $event): void
    {
    }

    #[\Override]
    public function postProgress(MattermostInboundEvent $event, string $message): void
    {
        $this->threadMessages[] = $message;
    }

    #[\Override]
    public function createPost(MattermostInboundEvent $event, string $message, array $props = []): string
    {
        $this->createdMessages[] = $message;

        return 'task-post';
    }

    #[\Override]
    public function updatePost(string $postId, string $message, array $props = []): bool
    {
        $this->updatedMessages[] = $message;

        return true;
    }
}

final readonly class DurableTaskModelSelector implements TaskModelSelector
{
    #[\Override]
    public function select(string $request): TaskModelSelection
    {
        return TaskModelSelection::fromRoute('sol-xhigh', 'Contained feature with several interacting changes.')
            ?? throw new \LogicException('Expected a valid test model selection.');
    }
}

final readonly class FailingTaskModelSelector implements TaskModelSelector
{
    #[\Override]
    public function select(string $request): TaskModelSelection
    {
        throw new \LogicException('Model selection must not run again for an existing session.');
    }
}
