<?php

namespace App\Tests\AgentTag\Mattermost;

use App\AgentTag\Mattermost\MattermostInboundEvent;
use App\AgentTag\Mattermost\MattermostNotifier;
use App\AgentTag\Mattermost\MattermostRunProgressSink;
use App\AgentTag\Runner\AgentRunnerProgress;
use App\AgentTag\Runner\AgentRunnerResult;
use App\Entity\AgentRun;
use App\Entity\ChatSession;
use PHPUnit\Framework\TestCase;

final class MattermostRunProgressSinkTest extends TestCase
{
    public function testItPreservesMarkdownLineBreaksAndCodeFences(): void
    {
        $notifier = new MarkdownTraceableMattermostNotifier();
        $sink = new MattermostRunProgressSink(
            $notifier,
            new MattermostInboundEvent('post', '', 'post', '', 'channel', 'O', 'team', 'user', ''),
            new AgentRun(new ChatSession('mattermost:team:channel:post', 'mattermost', 'team', 'channel', 'post', new \DateTimeImmutable()), AgentRun::STATUS_RUNNING, new \DateTimeImmutable()),
        );

        $message = "Plan:\n\n- inspect\n- patch\n\n```php\necho 'ok';\n```";
        $sink->onProgress(new AgentRunnerProgress('agent_message', $message));

        self::assertSame([$message], $notifier->progressMessages);
    }

    public function testItRefreshesTypingOnHeartbeat(): void
    {
        $notifier = new MarkdownTraceableMattermostNotifier();
        $sink = new MattermostRunProgressSink(
            $notifier,
            new MattermostInboundEvent('post', '', 'post', '', 'channel', 'O', 'team', 'user', ''),
            new AgentRun(new ChatSession('mattermost:team:channel:post', 'mattermost', 'team', 'channel', 'post', new \DateTimeImmutable()), AgentRun::STATUS_RUNNING, new \DateTimeImmutable()),
            typingRefreshIntervalSeconds: 0,
        );

        $sink->onHeartbeat();
        $sink->onHeartbeat();

        self::assertSame(2, $notifier->typingCount);
    }

    public function testItRefreshesTypingImmediatelyBeforePostingTheFinalMessage(): void
    {
        $notifier = new MarkdownTraceableMattermostNotifier();
        $sink = new MattermostRunProgressSink(
            $notifier,
            new MattermostInboundEvent('post', '', 'post', '', 'channel', 'O', 'team', 'user', ''),
            new AgentRun(new ChatSession('mattermost:team:channel:post', 'mattermost', 'team', 'channel', 'post', new \DateTimeImmutable()), AgentRun::STATUS_RUNNING, new \DateTimeImmutable()),
            typingRefreshIntervalSeconds: 300,
        );

        $sink->onHeartbeat();
        $sink->finish(new AgentRunnerResult(0, 'Final answer.', '', '', [], null));

        self::assertSame(2, $notifier->typingCount);
        self::assertSame(['Final answer.'], $notifier->progressMessages);
    }
}

final class MarkdownTraceableMattermostNotifier implements MattermostNotifier
{
    /**
     * @var list<string>
     */
    public array $progressMessages = [];

    public int $typingCount = 0;

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
