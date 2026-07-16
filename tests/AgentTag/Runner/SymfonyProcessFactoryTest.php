<?php

namespace App\Tests\AgentTag\Runner;

use App\AgentTag\Runner\SymfonyProcessFactory;
use PHPUnit\Framework\TestCase;

final class SymfonyProcessFactoryTest extends TestCase
{
    public function testItRemainsCompatibleWithAContainerCompiledBeforeTokenConfiguration(): void
    {
        $process = (new SymfonyProcessFactory())->create(
            [\PHP_BINARY, '-r', 'echo "ok";'],
            sys_get_temp_dir(),
            [],
            '',
            10,
        );

        self::assertSame(0, $process->run());
        self::assertSame('ok', $process->output());
    }

    public function testItPassesTheConfiguredGitHubTokenToTheProcess(): void
    {
        $process = (new SymfonyProcessFactory('configured-token'))->create(
            [\PHP_BINARY, '-r', 'echo getenv("GITHUB_PAT_TOKEN");'],
            sys_get_temp_dir(),
            [],
            '',
            10,
        );

        self::assertSame(0, $process->run());
        self::assertSame('configured-token', $process->output());
    }

    public function testAnExplicitProcessTokenTakesPrecedence(): void
    {
        $process = (new SymfonyProcessFactory('configured-token'))->create(
            [\PHP_BINARY, '-r', 'echo getenv("GITHUB_PAT_TOKEN");'],
            sys_get_temp_dir(),
            ['GITHUB_PAT_TOKEN' => 'process-token'],
            '',
            10,
        );

        self::assertSame(0, $process->run());
        self::assertSame('process-token', $process->output());
    }
}
