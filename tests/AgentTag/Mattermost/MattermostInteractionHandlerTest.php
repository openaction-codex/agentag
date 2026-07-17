<?php

namespace App\Tests\AgentTag\Mattermost;

use App\AgentTag\Agent\AgentProfile;
use App\AgentTag\Agent\AgentProfileProvider;
use App\AgentTag\Chat\ChatSessionReference;
use App\AgentTag\Chat\ConfiguredTagMentionDetector;
use App\AgentTag\Chat\InboundEventIdempotencyStore;
use App\AgentTag\Configuration\AgentTagSettings;
use App\AgentTag\Mattermost\MattermostInboundEvent;
use App\AgentTag\Mattermost\MattermostInteractionHandler;
use App\AgentTag\Mattermost\MattermostNotifier;
use App\AgentTag\Mattermost\MattermostSessionMapper;
use App\AgentTag\Mattermost\MattermostThreadContextProvider;
use App\AgentTag\Mattermost\TaskCardRenderer;
use App\AgentTag\Run\RunInterrupter;
use App\AgentTag\Runner\TaskModelSelection;
use App\AgentTag\Session\ChatSessionStore;
use App\AgentTag\Session\ChatThreadContext;
use App\AgentTag\Session\ChatThreadMessage;
use App\Entity\AgentRun;
use App\Entity\ChatSession;
use App\Message\PrepareAgentTaskMessage;
use App\Message\RunAgentRunMessage;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Generator\UrlGenerator;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

final class MattermostInteractionHandlerTest extends TestCase
{
    public function testItImmediatelyCreatesAPendingModelTaskCardAndQueuesSelection(): void
    {
        $notifier = new TraceableMattermostNotifier();
        $store = new TraceableChatSessionStore();
        $bus = new TraceableMessageBus();
        $handler = $this->handler($notifier, $store, $bus, new TraceableRunInterrupter());

        $result = $handler->handle($this->event());

        self::assertTrue($result->isHandled());
        self::assertSame([1], $bus->preparationRunIds);
        self::assertSame([], $bus->runIds);
        self::assertCount(1, $notifier->createdPosts);
        self::assertStringContainsString('**Request received**', $notifier->createdPosts[0]);
        self::assertStringContainsString('Model: **Deciding which model to use**', $notifier->createdPosts[0]);
        self::assertStringContainsString('→ Request received. Deciding which model to use.', $notifier->createdPosts[0]);
        $savedRun = array_pop($store->savedRuns);
        self::assertInstanceOf(AgentRun::class, $savedRun);
        self::assertSame('task-post-1', $savedRun->taskPostId());
        self::assertSame(['mattermost:team:channel:post'], $store->sessionKeys);
    }

    public function testItReusesTheSessionModelAndSkipsSelectionForAFollowUp(): void
    {
        $notifier = new TraceableMattermostNotifier();
        $store = new TraceableChatSessionStore();
        $store->modelSelection = TaskModelSelection::fromRoute('sol-xhigh', 'Selected for the first request in this thread.');
        $bus = new TraceableMessageBus();
        $handler = $this->handler($notifier, $store, $bus, new TraceableRunInterrupter());

        $handler->handle($this->event('@Codex now add the regression test'));

        self::assertSame([], $bus->preparationRunIds);
        self::assertSame([1], $bus->runIds);
        self::assertCount(1, $notifier->createdPosts);
        self::assertStringContainsString('Model: **GPT-5.6 Sol · xhigh**', $notifier->createdPosts[0]);
        self::assertStringContainsString('Continuing with the model selected for this session.', $notifier->createdPosts[0]);
        self::assertSame('sol-xhigh', $store->savedRuns[0]->modelSelection()->route);
    }

    public function testItTreatsANewerMessageAsSteeringForTheSameActiveTask(): void
    {
        $interrupter = new TraceableRunInterrupter();
        $interrupter->activeRun = $this->taskRun(7, AgentRun::STATUS_INTERRUPT_REQUESTED);
        $store = new TraceableChatSessionStore();
        $bus = new TraceableMessageBus();
        $handler = $this->handler(new TraceableMattermostNotifier(), $store, $bus, $interrupter);

        $handler->handle($this->event('@Codex focus on the backend'));

        self::assertSame(['focus on the backend'], $interrupter->steering);
        self::assertSame([], $store->sessionKeys);
        self::assertSame([], $bus->runIds);
    }

    public function testStopCancelsTheActiveTaskWithoutCreatingAnotherRun(): void
    {
        $interrupter = new TraceableRunInterrupter();
        $interrupter->activeRun = $this->taskRun(7, AgentRun::STATUS_INTERRUPT_REQUESTED);
        $store = new TraceableChatSessionStore();
        $handler = $this->handler(new TraceableMattermostNotifier(), $store, new TraceableMessageBus(), $interrupter);

        $handler->handle($this->event('@Codex stop'));

        self::assertSame(1, $interrupter->cancelCalls);
        self::assertSame([], $store->sessionKeys);
    }

    public function testRetryResumesTheLatestTaskInsteadOfCreatingANewOne(): void
    {
        $interrupter = new TraceableRunInterrupter();
        $interrupter->retryRun = $this->taskRun(8, AgentRun::STATUS_ACCEPTED);
        $store = new TraceableChatSessionStore();
        $bus = new TraceableMessageBus();
        $handler = $this->handler(new TraceableMattermostNotifier(), $store, $bus, $interrupter);

        $handler->handle($this->event('@Codex retry from the test step'));

        self::assertSame([8], $bus->runIds);
        self::assertSame([], $store->sessionKeys);
    }

    private function handler(
        TraceableMattermostNotifier $notifier,
        TraceableChatSessionStore $store,
        TraceableMessageBus $bus,
        TraceableRunInterrupter $interrupter,
    ): MattermostInteractionHandler {
        $settings = new AgentTagSettings('@Codex', '/tmp/workspace');

        return new MattermostInteractionHandler(
            new ConfiguredTagMentionDetector($settings),
            new MattermostSessionMapper(),
            new TestInboundEventIdempotencyStore(),
            $notifier,
            $store,
            new FixedMattermostThreadContextProvider(),
            new FixedAgentProfileProvider(),
            $bus,
            $interrupter,
            $this->renderer(),
            $settings,
        );
    }

    private function renderer(): TaskCardRenderer
    {
        $routes = new RouteCollection();
        $routes->add('agentag_mattermost_action', new Route('/integrations/mattermost/action'));

        return new TaskCardRenderer(new UrlGenerator($routes, new RequestContext('', 'GET', 'agentag.test', 'https')), 'secret');
    }

    private function event(string $text = '@Codex fix billing tests'): MattermostInboundEvent
    {
        return new MattermostInboundEvent('post', $text, 'post', '', 'channel', 'O', 'team', 'user', '', 'thomas');
    }

    private function taskRun(int $id, string $status): AgentRun
    {
        $run = new AgentRun(new ChatSession('mattermost:team:channel:post', 'team', 'channel', 'post', new \DateTimeImmutable()), $status, new \DateTimeImmutable());
        (new \ReflectionProperty(AgentRun::class, 'id'))->setValue($run, $id);

        return $run;
    }
}

final class TraceableChatSessionStore implements ChatSessionStore
{
    /** @var list<string> */
    public array $sessionKeys = [];
    /** @var list<AgentRun> */
    public array $savedRuns = [];
    public ?TaskModelSelection $modelSelection = null;

    #[\Override]
    public function recordRun(ChatSessionReference $reference, string $inputSummary, ChatThreadContext $threadContext, AgentProfile $agent, ?string $sourceEventId = null, ?string $requesterId = null): AgentRun
    {
        $this->sessionKeys[] = $reference->key();
        $session = new ChatSession($reference->key(), $reference->teamId(), $reference->channelId(), $reference->threadId(), new \DateTimeImmutable(), workspacePath: '/tmp/workspace');
        $run = new AgentRun($session, AgentRun::STATUS_ACCEPTED, new \DateTimeImmutable(), workspacePath: '/tmp/workspace');
        if (null !== $this->modelSelection) {
            $session->selectModel($this->modelSelection);
            $run->selectModel($this->modelSelection);
        }
        (new \ReflectionProperty(AgentRun::class, 'id'))->setValue($run, count($this->sessionKeys));

        return $run;
    }

    #[\Override]
    public function save(AgentRun $run): void
    {
        $this->savedRuns[] = $run;
    }
}

final class TestInboundEventIdempotencyStore implements InboundEventIdempotencyStore
{
    /** @var array<string, true> */
    private array $events = [];

    #[\Override]
    public function remember(string $eventId): bool
    {
        if (isset($this->events[$eventId])) {
            return false;
        }
        $this->events[$eventId] = true;

        return true;
    }
}

final readonly class FixedAgentProfileProvider implements AgentProfileProvider
{
    #[\Override]
    public function profile(): AgentProfile
    {
        return new AgentProfile('agent', '/tmp/workspace', null, 'codex-full-access', 1200);
    }
}

final readonly class FixedMattermostThreadContextProvider implements MattermostThreadContextProvider
{
    #[\Override]
    public function contextFor(MattermostInboundEvent $event): ChatThreadContext
    {
        return new ChatThreadContext([new ChatThreadMessage($event->postId(), $event->userId(), $event->text())]);
    }
}

final class TraceableMattermostNotifier implements MattermostNotifier
{
    /** @var list<string> */
    public array $createdPosts = [];
    /** @var list<string> */
    public array $updatedPosts = [];

    #[\Override]
    public function showTyping(MattermostInboundEvent $event): void
    {
    }

    #[\Override]
    public function postProgress(MattermostInboundEvent $event, string $message): void
    {
    }

    #[\Override]
    public function createPost(MattermostInboundEvent $event, string $message, array $props = []): string
    {
        $this->createdPosts[] = $message;

        return 'task-post-'.count($this->createdPosts);
    }

    #[\Override]
    public function updatePost(string $postId, string $message, array $props = []): bool
    {
        $this->updatedPosts[] = $message;

        return true;
    }
}

final class TraceableRunInterrupter implements RunInterrupter
{
    public ?AgentRun $activeRun = null;
    public ?AgentRun $retryRun = null;
    public int $cancelCalls = 0;
    /** @var list<string> */
    public array $steering = [];

    #[\Override]
    public function cancelActiveRun(ChatSessionReference $reference, string $sourceEventId, string $requesterId): ?AgentRun
    {
        ++$this->cancelCalls;

        return $this->activeRun;
    }

    #[\Override]
    public function steerActiveRun(ChatSessionReference $reference, string $instruction, string $sourceEventId, string $requesterId): ?AgentRun
    {
        $this->steering[] = $instruction;

        return $this->activeRun;
    }

    #[\Override]
    public function retryLatestRun(ChatSessionReference $reference, string $instruction): ?AgentRun
    {
        return $this->retryRun;
    }
}

final class TraceableMessageBus implements MessageBusInterface
{
    /** @var list<int> */
    public array $runIds = [];
    /** @var list<int> */
    public array $preparationRunIds = [];

    #[\Override]
    public function dispatch(object $message, array $stamps = []): Envelope
    {
        if ($message instanceof RunAgentRunMessage) {
            $this->runIds[] = $message->runId();
        }
        if ($message instanceof PrepareAgentTaskMessage) {
            $this->preparationRunIds[] = $message->runId();
        }

        return new Envelope($message, $stamps);
    }
}
