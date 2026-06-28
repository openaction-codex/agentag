<?php

namespace App\Tests\AgentTag\Slack;

use App\AgentTag\Chat\ConfiguredTagMentionDetector;
use App\AgentTag\Chat\InMemoryInboundEventIdempotencyStore;
use App\AgentTag\Configuration\AgentTagSettings;
use App\AgentTag\Slack\SlackInboundEvent;
use App\AgentTag\Slack\SlackInteractionHandler;
use App\AgentTag\Slack\SlackNotifier;
use App\AgentTag\Slack\SlackSessionMapper;
use PHPUnit\Framework\TestCase;

final class SlackInteractionHandlerTest extends TestCase
{
    public function testItPostsProgressForMentionedMessagesAndIgnoresDuplicates(): void
    {
        $notifier = new TraceableSlackNotifier();
        $handler = new SlackInteractionHandler(
            new ConfiguredTagMentionDetector(new AgentTagSettings('@Codex', '/tmp/workspace', '/tmp/workspace/workflows', '')),
            new SlackSessionMapper(),
            new InMemoryInboundEventIdempotencyStore(),
            $notifier,
        );

        $firstResult = $handler->handle($this->event());
        $secondResult = $handler->handle($this->event());

        self::assertTrue($firstResult->isHandled());
        self::assertFalse($secondResult->isHandled());
        self::assertSame(1, $notifier->typingCount);
        self::assertSame(['Accepted. I will continue this Slack thread as session `1700000000.000000`.'], $notifier->progressMessages);
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
