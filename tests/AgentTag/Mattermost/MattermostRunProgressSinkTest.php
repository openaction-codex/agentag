<?php

namespace App\Tests\AgentTag\Mattermost;

use App\AgentTag\Mattermost\MattermostInboundEvent;
use App\AgentTag\Mattermost\MattermostNotifier;
use App\AgentTag\Mattermost\MattermostRunProgressSink;
use App\AgentTag\Mattermost\TaskCardRenderer;
use App\AgentTag\Runner\AgentRunnerProgress;
use App\Entity\AgentRun;
use App\Entity\ChatSession;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\Generator\UrlGenerator;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

final class MattermostRunProgressSinkTest extends TestCase
{
    public function testItUpdatesOneTaskCardFromMeaningfulAgentMessages(): void
    {
        $notifier = new ProgressTraceableMattermostNotifier();
        $run = $this->task();
        $sink = $this->sink($notifier, $run);

        $sink->onProgress(new AgentRunnerProgress('command_execution', 'phpunit output'));
        $sink->onProgress(new AgentRunnerProgress('agent_message', 'Reproduced three billing test failures. I am tracing rounding now.'));

        self::assertCount(1, $notifier->updatedPosts);
        self::assertStringContainsString('Reproduced three billing test failures.', $notifier->updatedPosts[0]);
        self::assertStringNotContainsString('phpunit output', $notifier->updatedPosts[0]);
        $attachments = $notifier->updatedProps[0]['attachments'] ?? null;
        self::assertIsArray($attachments);
        $attachment = $attachments[0] ?? null;
        self::assertIsArray($attachment);
        $actions = $attachment['actions'] ?? null;
        self::assertIsArray($actions);
        self::assertSame(['Stop'], array_column($actions, 'name'));
        self::assertSame([], $notifier->threadMessages);
    }

    public function testFinishKeepsTheStepsAndPostsTheAnswerAfterTheCardOnlyOnce(): void
    {
        $notifier = new ProgressTraceableMattermostNotifier();
        $run = $this->task();
        $run->recordRunnerResult(AgentRun::STATUS_COMPLETED, "Cause\nRounding order.\n\nVerification\n• 428 tests passed", '', '/tmp/workspace', [], 0, null);
        $sink = $this->sink($notifier, $run);

        $sink->finish();
        $sink->finish();

        self::assertCount(2, $notifier->updatedPosts);
        self::assertStringContainsString('✅ **Fix billing tests**', $notifier->updatedPosts[0]);
        self::assertStringContainsString('✓ Workspace ready', $notifier->updatedPosts[0]);
        self::assertStringContainsString('✓ Complete task and verify results', $notifier->updatedPosts[0]);
        self::assertStringNotContainsString('428 tests passed', $notifier->updatedPosts[0]);
        self::assertSame([], $notifier->updatedProps[0]['attachments']);
        self::assertSame(["Cause\nRounding order.\n\nVerification\n• 428 tests passed"], $notifier->createdMessages);
        self::assertSame('answer-post', $run->answerPostId());
    }

    private function sink(ProgressTraceableMattermostNotifier $notifier, AgentRun $run): MattermostRunProgressSink
    {
        return new MattermostRunProgressSink(
            $notifier,
            new MattermostInboundEvent('post', '', 'post', '', 'channel', 'O', 'team', 'user', ''),
            $run,
            $this->renderer(),
            $this->createStub(EntityManagerInterface::class),
            minimumIntervalSeconds: 0,
        );
    }

    private function task(): AgentRun
    {
        $run = new AgentRun(new ChatSession('mattermost:t:c:p', 't', 'c', 'p', new \DateTimeImmutable()), AgentRun::STATUS_RUNNING, new \DateTimeImmutable(), requesterId: 'user', workspacePath: '/tmp/workspace');
        (new \ReflectionProperty(AgentRun::class, 'id'))->setValue($run, 4);
        $run->initializeTask('Fix billing tests', 'Workspace ready', 'thomas', new \DateTimeImmutable('+1 day'), 2, 60, 'milestones');
        $run->assignTaskPost('task-post');

        return $run;
    }

    private function renderer(): TaskCardRenderer
    {
        $routes = new RouteCollection();
        $routes->add('agentag_mattermost_action', new Route('/integrations/mattermost/action'));

        return new TaskCardRenderer(new UrlGenerator($routes, new RequestContext('', 'GET', 'agentag.test', 'https')), 'secret');
    }
}

final class ProgressTraceableMattermostNotifier implements MattermostNotifier
{
    /** @var list<string> */
    public array $updatedPosts = [];
    /** @var list<string> */
    public array $threadMessages = [];
    /** @var list<string> */
    public array $createdMessages = [];
    /** @var list<array<string, mixed>> */
    public array $updatedProps = [];

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

        return 'answer-post';
    }

    #[\Override]
    public function updatePost(string $postId, string $message, array $props = []): bool
    {
        $this->updatedPosts[] = $message;
        $this->updatedProps[] = $props;

        return true;
    }
}
