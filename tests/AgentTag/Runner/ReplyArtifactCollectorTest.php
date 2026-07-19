<?php

namespace App\Tests\AgentTag\Runner;

use App\AgentTag\Runner\ReplyArtifactCollector;
use PHPUnit\Framework\TestCase;

final class ReplyArtifactCollectorTest extends TestCase
{
    private string $directory;

    #[\Override]
    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir().'/agentag-artifacts-'.bin2hex(random_bytes(6));
        mkdir($this->directory.'/reply-files', 0770, true);
    }

    #[\Override]
    protected function tearDown(): void
    {
        foreach (glob($this->directory.'/reply-files/{,.}*', \GLOB_BRACE) ?: [] as $entry) {
            if (!in_array(basename($entry), ['.', '..'], true)) {
                unlink($entry);
            }
        }
        rmdir($this->directory.'/reply-files');
        rmdir($this->directory);
    }

    public function testItIgnoresHiddenTemporaryAndSymlinkedFiles(): void
    {
        file_put_contents($this->directory.'/reply-files/result.txt', 'complete');
        file_put_contents($this->directory.'/reply-files/result.part', 'partial');
        file_put_contents($this->directory.'/reply-files/.secret', 'hidden');
        symlink('/etc/hosts', $this->directory.'/reply-files/hosts.txt');

        $artifacts = (new ReplyArtifactCollector())->collect($this->directory);

        self::assertCount(1, $artifacts);
        self::assertSame('result.txt', $artifacts[0]->label());
        self::assertSame(hash('sha256', 'complete'), $artifacts[0]->sha256());
    }

    public function testItCapsAReplyAtFiveFiles(): void
    {
        foreach (range(1, 6) as $number) {
            file_put_contents($this->directory.'/reply-files/file-'.$number.'.txt', (string) $number);
        }

        $artifacts = (new ReplyArtifactCollector())->collect($this->directory);

        self::assertCount(ReplyArtifactCollector::MAX_FILES, $artifacts);
        self::assertSame([
            'file-1.txt',
            'file-2.txt',
            'file-3.txt',
            'file-4.txt',
            'file-5.txt',
        ], array_map(static fn ($artifact): string => $artifact->label(), $artifacts));
    }
}
