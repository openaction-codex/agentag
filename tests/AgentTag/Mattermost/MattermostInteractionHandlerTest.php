<?php

namespace App\Tests\AgentTag\Mattermost;

use App\AgentTag\Agent\AgentProfile;
use App\AgentTag\Agent\AgentProfileProvider;
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
use App\Entity\AgentRun;
use App\Entity\ChatSession;
use App\Message\RunAgentRunMessage;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

final class MattermostInteractionHandlerTest extends TestCase
{
    public function testItPostsProgressForMentionedMessagesAndIgnoresDuplicates(): void
    {
        $notifier = new TraceableMattermostNotifier();
        $sessionStore = new TraceableChatSessionStore();
        $messageBus = new TraceableMessageBus();
        $handler = new MattermostInteractionHandler(
            new ConfiguredTagMentionDetector(new AgentTagSettings('@Codex', '/tmp/workspace')),
            new MattermostSessionMapper(),
            new InMemoryInboundEventIdempotencyStore(),
            $notifier,
            $sessionStore,
            new FixedMattermostThreadContextProvider(),
            new FixedAgentProfileProvider(),
            $messageBus,
        );

        $firstResult = $handler->handle($this->event());
        $secondResult = $handler->handle($this->event());

        self::assertTrue($firstResult->isHandled());
        self::assertFalse($secondResult->isHandled());
        self::assertSame(1, $notifier->typingCount);
        self::assertSame([], $notifier->progressMessages);
        self::assertSame([1], $messageBus->runIds);
        self::assertSame(['mattermost:team:channel:post'], $sessionStore->sessionKeys);
        self::assertSame(['agent'], $sessionStore->agentNames);
        self::assertSame(['post'], $sessionStore->sourceEventIds);
        self::assertSame(['user'], $sessionStore->requesterIds);
        self::assertSame([['root text', '@Codex help']], $sessionStore->threadMessages);
    }

    public function testItReturnsConfigurationErrorsInChat(): void
    {
        $notifier = new TraceableMattermostNotifier();
        $handler = new MattermostInteractionHandler(
            new ConfiguredTagMentionDetector(new AgentTagSettings('@Codex', '/tmp/workspace')),
            new MattermostSessionMapper(),
            new InMemoryInboundEventIdempotencyStore(),
            $notifier,
            new FailingChatSessionStore('Workspace template directory "/tmp/workspace" does not exist.'),
            new FixedMattermostThreadContextProvider(),
            new FixedAgentProfileProvider(),
            new TraceableMessageBus(),
        );

        $result = $handler->handle($this->event());

        self::assertTrue($result->isHandled());
        self::assertSame(['Workspace template directory "/tmp/workspace" does not exist.'], $notifier->progressMessages);
    }

    public function testItIgnoresMessagesWithoutTheConfiguredMention(): void
    {
        $notifier = new TraceableMattermostNotifier();
        $sessionStore = new TraceableChatSessionStore();
        $handler = new MattermostInteractionHandler(
            new ConfiguredTagMentionDetector(new AgentTagSettings('@Codex', '/tmp/workspace')),
            new MattermostSessionMapper(),
            new InMemoryInboundEventIdempotencyStore(),
            $notifier,
            $sessionStore,
            new FixedMattermostThreadContextProvider(),
            new FixedAgentProfileProvider(),
            new TraceableMessageBus(),
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
    public array $agentNames = [];

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
        AgentProfile $agent,
        ?string $sourceEventId = null,
        ?string $requesterId = null,
    ): AgentRun {
        $this->sessionKeys[] = $reference->key();
        $this->agentNames[] = $agent->name();
        $this->sourceEventIds[] = $sourceEventId;
        $this->requesterIds[] = $requesterId;
        $this->threadMessages[] = array_map(
            static fn (ChatThreadMessage $message): string => $message->text(),
            $threadContext->messages(),
        );

        $run = new AgentRun(
            new ChatSession($reference->key(), $reference->platform(), $reference->teamId(), $reference->channelId(), $reference->threadId(), new \DateTimeImmutable()),
            'accepted',
            new \DateTimeImmutable(),
        );
        $reflection = new \ReflectionProperty(AgentRun::class, 'id');
        $reflection->setValue($run, count($this->sessionKeys));

        return $run;
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
        AgentProfile $agent,
        ?string $sourceEventId = null,
        ?string $requesterId = null,
    ): AgentRun {
        throw new \InvalidArgumentException($this->message);
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

final class TraceableMessageBus implements MessageBusInterface
{
    /**
     * @var list<int>
     */
    public array $runIds = [];

    #[\Override]
    public function dispatch(object $message, array $stamps = []): Envelope
    {
        if ($message instanceof RunAgentRunMessage) {
            $this->runIds[] = $message->runId();
        }

        return new Envelope($message, $stamps);
    }
}
