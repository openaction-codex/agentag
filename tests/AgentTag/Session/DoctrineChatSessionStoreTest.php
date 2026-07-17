<?php

namespace App\Tests\AgentTag\Session;

use App\AgentTag\Agent\AgentProfileProvider;
use App\AgentTag\Chat\ChatSessionReference;
use App\AgentTag\Runner\TaskModelSelection;
use App\AgentTag\Session\ChatSessionStore;
use App\AgentTag\Session\ChatThreadContext;
use App\AgentTag\Session\ChatThreadMessage;
use App\Entity\AgentRun;
use App\Entity\ChatSession;
use App\Tests\RefreshDatabaseTrait;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class DoctrineChatSessionStoreTest extends KernelTestCase
{
    use RefreshDatabaseTrait;

    #[\Override]
    protected function setUp(): void
    {
        self::bootKernel();
        $this->refreshDatabase();
        $this->prepareWorkspaceTemplate();
    }

    public function testItCreatesOneSessionAndAppendsRunsForTheSameThread(): void
    {
        $store = static::getContainer()->get(ChatSessionStore::class);
        self::assertInstanceOf(ChatSessionStore::class, $store);
        $agent = $this->agent();

        $reference = new ChatSessionReference('team-id', 'channel-id', 'root-id');

        $store->recordRun($reference, 'first input token=secret-token', new ChatThreadContext([
            new ChatThreadMessage('root-id', 'user-a', '@Codex help password=hunter2'),
        ]), $agent);
        $store->recordRun($reference, 'second input', new ChatThreadContext([
            new ChatThreadMessage('root-id', 'user-a', '@Codex help'),
            new ChatThreadMessage('reply-id', 'user-b', 'continue with Bearer abcdefghijklmnop'),
        ]), $agent);

        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $entityManager);

        $sessions = $entityManager->getRepository(ChatSession::class)->findAll();
        $runs = $entityManager->getRepository(AgentRun::class)->findBy([], ['id' => 'ASC']);

        self::assertCount(1, $sessions);
        self::assertCount(2, $runs);

        $session = $sessions[0];
        self::assertSame('mattermost:team-id:channel-id:root-id', $session->sessionKey());
        self::assertSame('team-id', $session->teamId());
        self::assertSame('channel-id', $session->channelId());
        self::assertSame('root-id', $session->threadId());
        self::assertNotNull($session->workspacePath());
        self::assertFileExists($session->workspacePath().'/AGENTS.md');

        self::assertSame($session, $runs[0]->session());
        self::assertSame('accepted', $runs[0]->status());
        self::assertSame('agent', $runs[0]->workflowName());
        self::assertNull($runs[0]->workflowVersion());
        self::assertNull($runs[0]->workflowRevision());
        self::assertSame($session->workspacePath(), $runs[0]->workspacePath());
        self::assertSame($session->workspacePath(), $runs[1]->workspacePath());
        self::assertSame('first input token=[REDACTED]', $runs[0]->inputSummary());
        self::assertStringContainsString('Agent: agent', (string) $runs[0]->contextSnapshot());
        self::assertStringContainsString('Workspace template:', (string) $runs[0]->contextSnapshot());
        self::assertStringContainsString('Session workspace: '.$session->workspacePath(), (string) $runs[0]->contextSnapshot());
        self::assertStringContainsString('Thread messages:', (string) $runs[0]->contextSnapshot());
        self::assertStringContainsString('password=[REDACTED]', (string) $runs[0]->contextSnapshot());
        self::assertStringNotContainsString('hunter2', (string) $runs[0]->contextSnapshot());
        self::assertSame('second input', $runs[1]->inputSummary());
        self::assertStringContainsString('Prior run summaries:', (string) $runs[1]->contextSnapshot());
        self::assertStringContainsString('first input token=[REDACTED]', (string) $runs[1]->contextSnapshot());
        self::assertStringContainsString('Bearer [REDACTED]', (string) $runs[1]->contextSnapshot());
    }

    public function testItReusesTheFirstSessionModelOnlyWithinTheSameThread(): void
    {
        $store = static::getContainer()->get(ChatSessionStore::class);
        self::assertInstanceOf(ChatSessionStore::class, $store);
        $agent = $this->agent();
        $thread = new ChatSessionReference('team-id', 'channel-id', 'root-id');

        $firstRun = $store->recordRun($thread, 'first request', new ChatThreadContext([]), $agent);
        $selection = TaskModelSelection::fromRoute('sol-xhigh', 'Selected for the first request in this thread.')
            ?? throw new \LogicException('Expected a valid test model selection.');
        $firstRun->session()->selectModel($selection);
        $firstRun->selectModel($selection);
        $store->save($firstRun);

        $followUp = $store->recordRun($thread, 'follow-up request', new ChatThreadContext([]), $agent);
        $newThread = $store->recordRun(
            new ChatSessionReference('team-id', 'channel-id', 'different-root-id'),
            'new session request',
            new ChatThreadContext([]),
            $agent,
        );

        self::assertTrue($followUp->hasModelSelection());
        self::assertSame('sol-xhigh', $followUp->modelSelection()->route);
        self::assertSame('Selected for the first request in this thread.', $followUp->modelSelection()->reason);
        self::assertFalse($newThread->hasModelSelection());
        self::assertNull($newThread->session()->modelSelection());
    }

    private function agent(): \App\AgentTag\Agent\AgentProfile
    {
        $provider = static::getContainer()->get(AgentProfileProvider::class);
        self::assertInstanceOf(AgentProfileProvider::class, $provider);

        return $provider->profile();
    }

    private function prepareWorkspaceTemplate(): void
    {
        $projectDirectory = static::getContainer()->getParameter('kernel.project_dir');
        if (!is_string($projectDirectory)) {
            throw new \LogicException('Kernel project directory must be available.');
        }

        $workspacePath = $projectDirectory.'/var/test-workspace';
        $this->removeDirectory($workspacePath);
        mkdir($workspacePath, 0777, true);
        file_put_contents($workspacePath.'/AGENTS.md', 'Use the shared workspace instructions.');
    }

    private function removeDirectory(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($files as $file) {
            if (!$file instanceof \SplFileInfo) {
                continue;
            }

            $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        }

        rmdir($path);
    }
}
