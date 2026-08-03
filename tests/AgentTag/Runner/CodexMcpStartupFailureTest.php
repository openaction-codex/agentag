<?php

namespace App\Tests\AgentTag\Runner;

use App\AgentTag\Runner\CodexJsonEventParser;
use PHPUnit\Framework\TestCase;

final class CodexMcpStartupFailureTest extends TestCase
{
    public function testItReportsAndDeduplicatesMcpStartupFailuresFromStderr(): void
    {
        $parser = new CodexJsonEventParser();

        $events = $parser->consumeStderr("MCP client for `oa-ecologistes` failed to initialize: request timed out\nMCP client for `oa-ecologistes` failed to initialize: request timed out\n");

        self::assertCount(1, $events);
        self::assertSame('mcp_startup_failed', $events[0]->type());
        self::assertSame('The MCP server "oa-ecologistes" did not load; the task will continue without its tools.', $events[0]->message());
        self::assertSame(['server' => 'oa-ecologistes'], $events[0]->context());
    }

    public function testItReportsMcpStartupFailuresFromJsonEvents(): void
    {
        $parser = new CodexJsonEventParser();

        $events = $parser->consume("{\"type\":\"mcp_server_startup_failed\",\"server_name\":\"oa-ecologistes\",\"error\":\"startup timeout\"}\n");

        self::assertCount(1, $events);
        self::assertSame('mcp_startup_failed', $events[0]->type());
        self::assertSame(['server' => 'oa-ecologistes'], $events[0]->context());
    }
}
