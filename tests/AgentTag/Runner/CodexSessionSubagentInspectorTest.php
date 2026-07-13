<?php

namespace App\Tests\AgentTag\Runner;

use App\AgentTag\Runner\CodexSessionSubagentInspector;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;

final class CodexSessionSubagentInspectorTest extends TestCase
{
    private string $codexHome;

    #[\Override]
    protected function setUp(): void
    {
        $this->codexHome = sys_get_temp_dir().'/agentag-codex-home-'.bin2hex(random_bytes(5));
        mkdir($this->codexHome.'/sessions/2026/07/13', 0777, true);
    }

    #[\Override]
    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->codexHome);
    }

    public function testItReadsTheActualAgentModelAndEffortFromTheChildSession(): void
    {
        $threadId = '019f5b58-902e-7132-9185-049c23e5cc7b';
        $path = $this->codexHome.'/sessions/2026/07/13/rollout-2026-07-13T11-59-18-'.$threadId.'.jsonl';
        file_put_contents($path, implode("\n", [
            json_encode(['type' => 'session_meta', 'payload' => [
                'agent_role' => 'sol-xhigh',
                'source' => ['subagent' => ['thread_spawn' => ['agent_role' => 'sol-xhigh']]],
            ]], \JSON_THROW_ON_ERROR),
            json_encode(['type' => 'turn_context', 'payload' => [
                'model' => 'gpt-5.6-sol',
                'effort' => 'xhigh',
            ]], \JSON_THROW_ON_ERROR),
        ]));

        $metadata = (new CodexSessionSubagentInspector())->inspect($threadId, $this->codexHome);

        self::assertNotNull($metadata);
        self::assertSame($threadId, $metadata->threadId);
        self::assertSame('sol-xhigh', $metadata->agent);
        self::assertSame('gpt-5.6-sol', $metadata->model);
        self::assertSame('xhigh', $metadata->reasoningEffort);
    }

    public function testItStreamsOnlyCompleteAgentMessagesAfterTheGivenOffset(): void
    {
        $threadId = '019f5b58-902e-7132-9185-049c23e5cc7b';
        $path = $this->codexHome.'/sessions/2026/07/13/rollout-2026-07-13T11-59-18-'.$threadId.'.jsonl';
        $firstMessage = json_encode(['type' => 'event_msg', 'payload' => [
            'type' => 'agent_message',
            'message' => 'Done: issue reproduced · Doing: tracing the call path · Next: patch the handler',
        ]], \JSON_THROW_ON_ERROR);
        $ignored = json_encode(['type' => 'event_msg', 'payload' => [
            'type' => 'token_count',
            'message' => 'not user-visible',
        ]], \JSON_THROW_ON_ERROR);
        $secondMessage = json_encode(['type' => 'event_msg', 'payload' => [
            'type' => 'agent_message',
            'message' => 'Done: handler patched · Doing: focused tests · Next: review the diff',
        ]], \JSON_THROW_ON_ERROR);
        file_put_contents($path, $firstMessage."\n".$ignored."\n".$secondMessage);

        $inspector = new CodexSessionSubagentInspector();
        $first = $inspector->progressSince($threadId, $this->codexHome, 0);

        self::assertSame(['Done: issue reproduced · Doing: tracing the call path · Next: patch the handler'], $first->messages);
        self::assertSame(strlen($firstMessage."\n".$ignored."\n"), $first->nextOffset);

        file_put_contents($path, "\n", \FILE_APPEND);
        $second = $inspector->progressSince($threadId, $this->codexHome, $first->nextOffset);

        self::assertSame(['Done: handler patched · Doing: focused tests · Next: review the diff'], $second->messages);
        self::assertSame(filesize($path), $second->nextOffset);
    }
}
