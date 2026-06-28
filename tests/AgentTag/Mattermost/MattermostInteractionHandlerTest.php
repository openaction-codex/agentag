<?php

namespace App\Tests\AgentTag\Mattermost;

use App\AgentTag\Chat\ChatSessionReference;
use App\AgentTag\Chat\ConfiguredTagMentionDetector;
use App\AgentTag\Chat\InMemoryInboundEventIdempotencyStore;
use App\AgentTag\Configuration\AgentTagSettings;
use App\AgentTag\Mattermost\MattermostInboundEvent;
use App\AgentTag\Mattermost\MattermostInteractionHandler;
use App\AgentTag\Mattermost\MattermostNotifier;
use App\AgentTag\Mattermost\MattermostSessionMapper;
use App\AgentTag\Mattermost\MattermostThreadContextProvider;
use App\AgentTag\Session\ChatSessionStore;
use App\AgentTag\Session\ChatThreadContext;
use App\AgentTag\Session\ChatThreadMessage;
use App\AgentTag\Workflow\WorkflowDefinition;
use App\AgentTag\Workflow\WorkflowSelection;
use App\AgentTag\Workflow\WorkflowSelector;
use App\Entity\AgentRun;
use App\Entity\ChatSession;
use PHPUnit\Framework\TestCase;

final class MattermostInteractionHandlerTest extends TestCase
{
    public function testItPostsProgressForMentionedMessagesAndIgnoresDuplicates(): void
    {
        $notifier = new TraceableMattermostNotifier();
        $sessionStore = new TraceableChatSessionStore();
        $handler = new MattermostInteractionHandler(
            new ConfiguredTagMentionDetector(new AgentTagSettings('@Codex', '/tmp/workspace', '/tmp/workspace/workflows', '')),
            new MattermostSessionMapper(),
            new InMemoryInboundEventIdempotencyStore(),
            $notifier,
            $sessionStore,
            new FixedMattermostThreadContextProvider(),
            new FixedWorkflowSelector(WorkflowSelection::selected($this->workflow())),
        );

        $firstResult = $handler->handle($this->event());
        $secondResult = $handler->handle($this->event());

        self::assertTrue($firstResult->isHandled());
        self::assertFalse($secondResult->isHandled());
        self::assertSame(1, $notifier->typingCount);
        self::assertSame(['Accepted workflow `developer`. I will continue this Mattermost thread as session `post`.'], $notifier->progressMessages);
        self::assertSame(['mattermost:team:channel:post'], $sessionStore->sessionKeys);
        self::assertSame(['developer'], $sessionStore->workflowNames);
        self::assertSame(['post'], $sessionStore->sourceEventIds);
        self::assertSame(['user'], $sessionStore->requesterIds);
        self::assertSame([['root text', '@Codex help']], $sessionStore->threadMessages);
    }

    public function testItReturnsWorkflowOptionsWhenSelectionFails(): void
    {
        $notifier = new TraceableMattermostNotifier();
        $sessionStore = new TraceableChatSessionStore();
        $handler = new MattermostInteractionHandler(
            new ConfiguredTagMentionDetector(new AgentTagSettings('@Codex', '/tmp/workspace', '/tmp/workspace/workflows', '')),
            new MattermostSessionMapper(),
            new InMemoryInboundEventIdempotencyStore(),
            $notifier,
            $sessionStore,
            new FixedMattermostThreadContextProvider(),
            new FixedWorkflowSelector(WorkflowSelection::unselected('Unknown workflow `sales`. Available workflows: `developer`.')),
        );

        $result = $handler->handle($this->event(text: '@Codex workflow:sales help'));

        self::assertTrue($result->isHandled());
        self::assertSame(['Unknown workflow `sales`. Available workflows: `developer`.'], $notifier->progressMessages);
        self::assertSame([], $sessionStore->sessionKeys);
    }

    public function testItReturnsConfigurationErrorsInChat(): void
    {
        $notifier = new TraceableMattermostNotifier();
        $handler = new MattermostInteractionHandler(
            new ConfiguredTagMentionDetector(new AgentTagSettings('@Codex', '/tmp/workspace', '/tmp/workspace/workflows', '')),
            new MattermostSessionMapper(),
            new InMemoryInboundEventIdempotencyStore(),
            $notifier,
            new FailingChatSessionStore('Unknown tool `missing` requested by workflow `developer`.'),
            new FixedMattermostThreadContextProvider(),
            new FixedWorkflowSelector(WorkflowSelection::selected($this->workflow())),
        );

        $result = $handler->handle($this->event());

        self::assertTrue($result->isHandled());
        self::assertSame(['Unknown tool `missing` requested by workflow `developer`.'], $notifier->progressMessages);
    }

    public function testItIgnoresMessagesWithoutTheConfiguredMention(): void
    {
        $notifier = new TraceableMattermostNotifier();
        $sessionStore = new TraceableChatSessionStore();
        $handler = new MattermostInteractionHandler(
            new ConfiguredTagMentionDetector(new AgentTagSettings('@Codex', '/tmp/workspace', '/tmp/workspace/workflows', '')),
            new MattermostSessionMapper(),
            new InMemoryInboundEventIdempotencyStore(),
            $notifier,
            $sessionStore,
            new FixedMattermostThreadContextProvider(),
            new FixedWorkflowSelector(WorkflowSelection::selected($this->workflow())),
        );

        $result = $handler->handle($this->event(text: 'hello'));

        self::assertFalse($result->isHandled());
        self::assertSame(0, $notifier->typingCount);
        self::assertSame([], $notifier->progressMessages);
        self::assertSame([], $sessionStore->sessionKeys);
    }

    private function event(string $text = '@Codex help'): MattermostInboundEvent
    {
        return new MattermostInboundEvent(
            'post',
            $text,
            'post',
            '',
            'channel',
            'O',
            'team',
            'user',
            '',
        );
    }

    private function workflow(): WorkflowDefinition
    {
        return WorkflowDefinition::fromArray(['name' => 'developer', 'version' => 'v1'], '/tmp/developer.yaml', 'abc123');
    }
}

final class TraceableChatSessionStore implements ChatSessionStore
{
    /**
     * @var list<string>
     */
    public array $sessionKeys = [];

    /**
     * @var list<string>
     */
    public array $workflowNames = [];

    /**
     * @var list<string|null>
     */
    public array $sourceEventIds = [];

    /**
     * @var list<string|null>
     */
    public array $requesterIds = [];

    /**
     * @var list<list<string>>
     */
    public array $threadMessages = [];

    #[\Override]
    public function recordRun(
        ChatSessionReference $reference,
        string $inputSummary,
        ChatThreadContext $threadContext,
        WorkflowDefinition $workflow,
        ?string $sourceEventId = null,
        ?string $requesterId = null,
    ): AgentRun {
        $this->sessionKeys[] = $reference->key();
        $this->workflowNames[] = $workflow->name();
        $this->sourceEventIds[] = $sourceEventId;
        $this->requesterIds[] = $requesterId;
        $this->threadMessages[] = array_map(
            static fn (ChatThreadMessage $message): string => $message->text(),
            $threadContext->messages(),
        );

        return new AgentRun(
            new ChatSession($reference->key(), $reference->platform(), $reference->teamId(), $reference->channelId(), $reference->threadId(), new \DateTimeImmutable()),
            'accepted',
            new \DateTimeImmutable(),
        );
    }
}

final readonly class FailingChatSessionStore implements ChatSessionStore
{
    public function __construct(private string $message)
    {
    }

    #[\Override]
    public function recordRun(
        ChatSessionReference $reference,
        string $inputSummary,
        ChatThreadContext $threadContext,
        WorkflowDefinition $workflow,
        ?string $sourceEventId = null,
        ?string $requesterId = null,
    ): AgentRun {
        throw new \InvalidArgumentException($this->message);
    }
}

final readonly class FixedWorkflowSelector implements WorkflowSelector
{
    public function __construct(private WorkflowSelection $selection)
    {
    }

    #[\Override]
    public function select(string $message): WorkflowSelection
    {
        return $this->selection;
    }
}

final readonly class FixedMattermostThreadContextProvider implements MattermostThreadContextProvider
{
    #[\Override]
    public function contextFor(MattermostInboundEvent $event): ChatThreadContext
    {
        return new ChatThreadContext([
            new ChatThreadMessage('root', 'user-a', 'root text'),
            new ChatThreadMessage($event->postId(), $event->userId(), $event->text()),
        ]);
    }
}

final class TraceableMattermostNotifier implements MattermostNotifier
{
    public int $typingCount = 0;

    /**
     * @var list<string>
     */
    public array $progressMessages = [];

    #[\Override]
    public function showTyping(MattermostInboundEvent $event): void
    {
        ++$this->typingCount;
    }

    #[\Override]
    public function postProgress(MattermostInboundEvent $event, string $message): void
    {
        $this->progressMessages[] = $message;
    }
}
