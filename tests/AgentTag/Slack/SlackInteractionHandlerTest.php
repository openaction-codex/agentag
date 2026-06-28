<?php

namespace App\Tests\AgentTag\Slack;

use App\AgentTag\Chat\ChatSessionReference;
use App\AgentTag\Chat\ConfiguredTagMentionDetector;
use App\AgentTag\Chat\InMemoryInboundEventIdempotencyStore;
use App\AgentTag\Configuration\AgentTagSettings;
use App\AgentTag\Session\ChatSessionStore;
use App\AgentTag\Session\ChatThreadContext;
use App\AgentTag\Session\ChatThreadMessage;
use App\AgentTag\Slack\CurrentSlackThreadContextProvider;
use App\AgentTag\Slack\SlackInboundEvent;
use App\AgentTag\Slack\SlackInteractionHandler;
use App\AgentTag\Slack\SlackNotifier;
use App\AgentTag\Slack\SlackSessionMapper;
use App\AgentTag\Workflow\WorkflowDefinition;
use App\AgentTag\Workflow\WorkflowSelection;
use App\AgentTag\Workflow\WorkflowSelector;
use App\Entity\AgentRun;
use App\Entity\ChatSession;
use PHPUnit\Framework\TestCase;

final class SlackInteractionHandlerTest extends TestCase
{
    public function testItPostsProgressForMentionedMessagesAndIgnoresDuplicates(): void
    {
        $notifier = new TraceableSlackNotifier();
        $sessionStore = new TraceableChatSessionStore();
        $handler = new SlackInteractionHandler(
            new ConfiguredTagMentionDetector(new AgentTagSettings('@Codex', '/tmp/workspace', '/tmp/workspace/workflows', '')),
            new SlackSessionMapper(),
            new InMemoryInboundEventIdempotencyStore(),
            $notifier,
            $sessionStore,
            new CurrentSlackThreadContextProvider(),
            new FixedWorkflowSelector(WorkflowSelection::selected($this->workflow())),
        );

        $firstResult = $handler->handle($this->event());
        $secondResult = $handler->handle($this->event());

        self::assertTrue($firstResult->isHandled());
        self::assertFalse($secondResult->isHandled());
        self::assertSame(1, $notifier->typingCount);
        self::assertSame(['Accepted workflow `developer`. I will continue this Slack thread as session `1700000000.000000`.'], $notifier->progressMessages);
        self::assertSame(['slack:T123:C123:1700000000.000000'], $sessionStore->sessionKeys);
        self::assertSame(['developer'], $sessionStore->workflowNames);
        self::assertSame(['Ev123'], $sessionStore->sourceEventIds);
        self::assertSame(['U123'], $sessionStore->requesterIds);
        self::assertSame([['@Codex help']], $sessionStore->threadMessages);
    }

    private function workflow(): WorkflowDefinition
    {
        return WorkflowDefinition::fromArray(['name' => 'developer', 'version' => 'v1'], '/tmp/developer.yaml', 'abc123');
    }

    private function event(): SlackInboundEvent
    {
        return new SlackInboundEvent(
            'Ev123',
            '@Codex help',
            '1700000000.000000',
            '',
            'C123',
            'T123',
            'U123',
            '',
        );
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

final class TraceableSlackNotifier implements SlackNotifier
{
    public int $typingCount = 0;

    /**
     * @var list<string>
     */
    public array $progressMessages = [];

    #[\Override]
    public function showTyping(SlackInboundEvent $event): void
    {
        ++$this->typingCount;
    }

    #[\Override]
    public function postProgress(SlackInboundEvent $event, string $message): void
    {
        $this->progressMessages[] = $message;
    }
}
