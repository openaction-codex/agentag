<?php

namespace App\Tests\AgentTag\Mattermost;

use App\AgentTag\Configuration\AgentTagSettings;
use App\AgentTag\Mattermost\InMemoryInboundEventIdempotencyStore;
use App\AgentTag\Mattermost\MattermostInboundEvent;
use App\AgentTag\Mattermost\MattermostInteractionHandler;
use App\AgentTag\Mattermost\MattermostMentionDetector;
use App\AgentTag\Mattermost\MattermostNotifier;
use App\AgentTag\Mattermost\MattermostSessionMapper;
use PHPUnit\Framework\TestCase;

final class MattermostInteractionHandlerTest extends TestCase
{
    public function testItPostsProgressForMentionedMessagesAndIgnoresDuplicates(): void
    {
        $notifier = new TraceableMattermostNotifier();
        $handler = new MattermostInteractionHandler(
            new MattermostMentionDetector(new AgentTagSettings('@Codex', '/tmp/workspace', '/tmp/workspace/workflows', '')),
            new MattermostSessionMapper(),
            new InMemoryInboundEventIdempotencyStore(),
            $notifier,
        );

        $firstResult = $handler->handle($this->event());
        $secondResult = $handler->handle($this->event());

        self::assertTrue($firstResult->isHandled());
        self::assertFalse($secondResult->isHandled());
        self::assertSame(1, $notifier->typingCount);
        self::assertSame(['Accepted. I will continue this Mattermost thread as session `post`.'], $notifier->progressMessages);
    }

    public function testItIgnoresMessagesWithoutTheConfiguredMention(): void
    {
        $notifier = new TraceableMattermostNotifier();
        $handler = new MattermostInteractionHandler(
            new MattermostMentionDetector(new AgentTagSettings('@Codex', '/tmp/workspace', '/tmp/workspace/workflows', '')),
            new MattermostSessionMapper(),
            new InMemoryInboundEventIdempotencyStore(),
            $notifier,
        );

        $result = $handler->handle($this->event(text: 'hello'));

        self::assertFalse($result->isHandled());
        self::assertSame(0, $notifier->typingCount);
        self::assertSame([], $notifier->progressMessages);
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
