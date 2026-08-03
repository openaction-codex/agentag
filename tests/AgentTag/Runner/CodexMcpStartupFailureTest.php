<?php

namespace App\Tests\AgentTag\Runner;

use App\AgentTag\Runner\CodexJsonEventParser;
use PHPUnit\Framework\TestCase;

final class CodexMcpStartupFailureTest extends TestCase
{
    public function testItReportsAndDeduplicatesMcpStartupFailuresFromStderr(): void
    {
        $parser = new CodexJsonEventParser();

        self::assertSame([], $parser->consumeStderr("MCP startup failed: request timed out\nMCP startup failed: request timed out\n"));
        $events = $parser->flush();

        self::assertCount(1, $events);
        self::assertSame('mcp_startup_failed', $events[0]->type());
        self::assertSame('An MCP server did not load; the task will continue without its tools.', $events[0]->message());
        self::assertSame([], $events[0]->context());
    }

    public function testItReportsMcpStartupFailuresFromJsonEvents(): void
    {
        $parser = new CodexJsonEventParser();

        $events = $parser->consume("{\"type\":\"mcp_server_startup_failed\",\"server_name\":\"oa-ecologistes\",\"error\":\"startup timeout\"}\n");

        self::assertCount(1, $events);
        self::assertSame('mcp_startup_failed', $events[0]->type());
        self::assertSame(['server' => 'oa-ecologistes'], $events[0]->context());
    }

    public function testItUsesTheServerReportedByTheAgentWhenCodexStderrIsUnattributed(): void
    {
        $parser = new CodexJsonEventParser();

        self::assertSame([], $parser->consumeStderr("MCP startup failed: request timed out\n"));
        $events = $parser->consume("{\"type\":\"item.completed\",\"item\":{\"type\":\"agent_message\",\"text\":\"Aucun serveur `oa_ecologistes` n’est exposé dans cette session.\"}}\n");

        self::assertCount(1, $events);
        self::assertSame('The MCP server "oa-ecologistes" did not load; the task will continue without its tools.', $events[0]->message());
        self::assertSame(['server' => 'oa-ecologistes'], $events[0]->context());
        self::assertSame([], $parser->flush());
    }
}
