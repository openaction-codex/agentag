<?php

namespace App\Tests\AgentTag\Mattermost;

use App\AgentTag\Mattermost\MattermostApiInputFileDownloader;
use App\AgentTag\Mattermost\MattermostApiSettings;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

use function Symfony\Component\String\b;

final class MattermostApiInputFileDownloaderTest extends TestCase
{
    private string $directory;

    #[\Override]
    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir().'/agentag-input-files-'.bin2hex(random_bytes(6));
    }

    #[\Override]
    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->directory);
    }

    public function testItDownloadsSanitizedAttachmentsAndRestoresThemOnEveryTurn(): void
    {
        $contents = "name,value\nA,1\n";
        $metadata = json_encode([[
            'id' => 'file-id',
            'name' => '../report.csv',
            'size' => b($contents)->length(),
        ]], JSON_THROW_ON_ERROR);
        $requests = [];
        $responses = [
            new MockResponse($metadata),
            new MockResponse($contents),
            new MockResponse($metadata),
            new MockResponse($contents),
        ];
        $client = new MockHttpClient(static function (string $method, string $url) use (&$requests, &$responses): MockResponse {
            $requests[] = [$method, $url];

            return array_shift($responses) ?? new MockResponse('', ['http_code' => 500]);
        });
        $downloader = new MattermostApiInputFileDownloader(
            $client,
            new MattermostApiSettings('https://mattermost.example.test', 'bot-token', 20),
        );

        $paths = $downloader->sync(['post-id', 'post-id'], $this->directory);
        file_put_contents($paths[0], 'tampered by an earlier agent turn');
        file_put_contents($this->directory.'/obsolete.txt', 'old input');
        $paths = $downloader->sync(['post-id'], $this->directory);

        self::assertSame([$this->directory.'/report.csv'], $paths);
        self::assertSame($contents, file_get_contents($paths[0]));
        self::assertFileDoesNotExist($this->directory.'/obsolete.txt');
        self::assertSame([
            ['GET', 'https://mattermost.example.test/api/v4/posts/post-id/files/info'],
            ['GET', 'https://mattermost.example.test/api/v4/files/file-id'],
            ['GET', 'https://mattermost.example.test/api/v4/posts/post-id/files/info'],
            ['GET', 'https://mattermost.example.test/api/v4/files/file-id'],
        ], $requests);
    }

    public function testItKeepsDuplicateAttachmentNamesDistinct(): void
    {
        $metadata = json_encode([
            ['id' => 'file-one', 'name' => 'notes.txt', 'size' => 3],
            ['id' => 'file-two', 'name' => 'notes.txt', 'size' => 3],
        ], JSON_THROW_ON_ERROR);
        $downloader = new MattermostApiInputFileDownloader(
            new MockHttpClient([
                new MockResponse($metadata),
                new MockResponse('one'),
                new MockResponse('two'),
            ]),
            new MattermostApiSettings('https://mattermost.example.test', 'bot-token', 20),
        );

        $paths = $downloader->sync(['post-id'], $this->directory);

        self::assertSame([
            $this->directory.'/notes.txt',
            $this->directory.'/notes-file-two.txt',
        ], $paths);
        self::assertSame('one', file_get_contents($paths[0]));
        self::assertSame('two', file_get_contents($paths[1]));
    }

    public function testItRejectsAttachmentsOverThePerFileLimitBeforeDownloading(): void
    {
        $downloader = new MattermostApiInputFileDownloader(
            new MockHttpClient(new MockResponse(json_encode([[
                'id' => 'huge-file',
                'name' => 'huge.zip',
                'size' => MattermostApiInputFileDownloader::MAX_FILE_BYTES + 1,
            ]], JSON_THROW_ON_ERROR))),
            new MattermostApiSettings('https://mattermost.example.test', 'bot-token', 20),
        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('exceeds the 100 MiB limit');

        $downloader->sync(['post-id'], $this->directory);
    }
}
