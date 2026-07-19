<?php

namespace App\AgentTag\Mattermost;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

use function Symfony\Component\String\b;
use function Symfony\Component\String\u;

final readonly class MattermostApiInputFileDownloader implements MattermostInputFileDownloader
{
    public const MAX_FILES = 20;
    public const MAX_FILE_BYTES = 104_857_600;
    public const MAX_TOTAL_BYTES = 262_144_000;

    public function __construct(
        private HttpClientInterface $httpClient,
        private MattermostApiSettings $settings,
        private ?LoggerInterface $logger = null,
    ) {
    }

    #[\Override]
    public function sync(array $postIds, string $inputFilesDirectory): array
    {
        $this->prepareDirectory($inputFilesDirectory);
        if (!$this->settings->enabled()) {
            $this->removeObsoleteFiles($inputFilesDirectory, []);

            return [];
        }

        $files = $this->filesForPosts(array_values(array_unique($postIds)));
        $paths = [];
        $usedNames = [];
        $totalBytes = 0;

        foreach ($files as $file) {
            $totalBytes += $file['size'];
            if ($totalBytes > self::MAX_TOTAL_BYTES) {
                throw new \RuntimeException(sprintf('Mattermost input files exceed the %d MiB total limit.', intdiv(self::MAX_TOTAL_BYTES, 1_048_576)));
            }

            $name = $this->uniqueName($file['name'], $file['id'], $usedNames);
            $path = $inputFilesDirectory.'/'.$name;
            $this->download($file['id'], $path, $file['size']);
            $paths[] = $path;
        }

        $this->removeObsoleteFiles($inputFilesDirectory, $paths);

        return $paths;
    }

    /**
     * @param list<string> $postIds
     *
     * @return list<array{id: string, name: string, size: int}>
     */
    private function filesForPosts(array $postIds): array
    {
        $files = [];
        $seenIds = [];
        foreach ($postIds as $postId) {
            if ('' === u($postId)->trim()->toString()) {
                continue;
            }

            $path = sprintf('/api/v4/posts/%s/files/info', rawurlencode($postId));
            $response = $this->request('GET', $path);

            try {
                if ($response->getStatusCode() >= 400) {
                    throw new \RuntimeException(sprintf('Mattermost could not list files attached to post %s.', $postId));
                }
                $payload = json_decode($response->getContent(false), true, flags: JSON_THROW_ON_ERROR);
            } catch (\JsonException|TransportExceptionInterface $exception) {
                throw new \RuntimeException(sprintf('Mattermost returned invalid file metadata for post %s.', $postId), previous: $exception);
            }
            if (!is_array($payload)) {
                throw new \RuntimeException(sprintf('Mattermost returned invalid file metadata for post %s.', $postId));
            }

            foreach ($payload as $item) {
                if (!is_array($item)
                    || !is_string($item['id'] ?? null)
                    || !is_string($item['name'] ?? null)
                    || !is_int($item['size'] ?? null)
                    || $item['size'] < 0) {
                    throw new \RuntimeException(sprintf('Mattermost returned incomplete file metadata for post %s.', $postId));
                }
                if (isset($seenIds[$item['id']])) {
                    continue;
                }
                if ($item['size'] > self::MAX_FILE_BYTES) {
                    throw new \RuntimeException(sprintf('Mattermost input file "%s" exceeds the %d MiB limit.', $item['name'], intdiv(self::MAX_FILE_BYTES, 1_048_576)));
                }
                if (self::MAX_FILES === count($files)) {
                    throw new \RuntimeException(sprintf('Mattermost task input is limited to %d files.', self::MAX_FILES));
                }

                $seenIds[$item['id']] = true;
                $files[] = [
                    'id' => $item['id'],
                    'name' => $item['name'],
                    'size' => $item['size'],
                ];
            }
        }

        return $files;
    }

    private function download(string $fileId, string $targetPath, int $expectedSize): void
    {
        $apiPath = sprintf('/api/v4/files/%s', rawurlencode($fileId));
        $response = $this->request('GET', $apiPath, 'application/octet-stream');
        try {
            if ($response->getStatusCode() >= 400) {
                throw new \RuntimeException(sprintf('Mattermost could not download input file %s.', $fileId));
            }
        } catch (TransportExceptionInterface $exception) {
            throw new \RuntimeException(sprintf('Mattermost input file %s could not be downloaded.', $fileId), previous: $exception);
        }

        $temporaryPath = $targetPath.'.part';
        $target = fopen($temporaryPath, 'wb');
        if (false === $target) {
            throw new \RuntimeException(sprintf('Could not create Mattermost input file "%s".', basename($targetPath)));
        }

        $written = 0;
        try {
            foreach ($this->httpClient->stream($response) as $chunk) {
                if ($chunk->isTimeout() || $chunk->isFirst() || $chunk->isLast()) {
                    continue;
                }
                $contents = $chunk->getContent();
                $written += b($contents)->length();
                if ($written > self::MAX_FILE_BYTES || $written > $expectedSize) {
                    throw new \RuntimeException(sprintf('Mattermost input file "%s" exceeded its declared size.', basename($targetPath)));
                }
                $this->write($target, $contents, $targetPath);
            }
        } catch (\Throwable $exception) {
            @unlink($temporaryPath);

            if ($exception instanceof \RuntimeException) {
                throw $exception;
            }

            throw new \RuntimeException(sprintf('Mattermost input file "%s" could not be downloaded.', basename($targetPath)), previous: $exception);
        } finally {
            fclose($target);
        }

        if ($written !== $expectedSize) {
            @unlink($temporaryPath);

            throw new \RuntimeException(sprintf('Mattermost input file "%s" was incomplete.', basename($targetPath)));
        }
        chmod($temporaryPath, 0640);
        if (!rename($temporaryPath, $targetPath)) {
            @unlink($temporaryPath);

            throw new \RuntimeException(sprintf('Could not finalize Mattermost input file "%s".', basename($targetPath)));
        }
    }

    /** @param resource $target */
    private function write($target, string $contents, string $targetPath): void
    {
        $offset = 0;
        while ($offset < b($contents)->length()) {
            $written = fwrite($target, b($contents)->slice($offset)->toString());
            if (false === $written || 0 === $written) {
                throw new \RuntimeException(sprintf('Could not write Mattermost input file "%s".', basename($targetPath)));
            }
            $offset += $written;
        }
    }

    private function request(string $method, string $path, string $accept = 'application/json'): ResponseInterface
    {
        try {
            return $this->httpClient->request($method, $this->settings->baseUrl().$path, [
                'auth_bearer' => $this->settings->botToken(),
                'headers' => ['Accept' => $accept],
            ]);
        } catch (TransportExceptionInterface $exception) {
            $this->logger?->warning('Mattermost input file request failed due to a transport error.', [
                'method' => $method,
                'path' => $path,
            ]);

            throw new \RuntimeException(sprintf('Mattermost input file request failed for %s.', $path), previous: $exception);
        }
    }

    private function prepareDirectory(string $directory): void
    {
        if (!is_dir($directory) && !mkdir($directory, 0770, true) && !is_dir($directory)) {
            throw new \RuntimeException(sprintf('Could not create Mattermost input file directory "%s".', $directory));
        }
        if (is_link($directory)) {
            throw new \RuntimeException('Mattermost input file directory must not be a symlink.');
        }
    }

    /**
     * @param array<string, true> $usedNames
     */
    private function uniqueName(string $name, string $fileId, array &$usedNames): string
    {
        $name = basename(u($name)->replace('\\', '/')->toString());
        $name = u($name)->replaceMatches('/[\x00-\x1F\x7F]/u', '_')->trim()->slice(0, 180)->toString();
        if ('' === $name || in_array($name, ['.', '..'], true)) {
            $name = 'attachment-'.u($fileId)->slice(0, 8)->toString();
        }

        $key = u($name)->lower()->toString();
        if (!isset($usedNames[$key])) {
            $usedNames[$key] = true;

            return $name;
        }

        $extension = pathinfo($name, PATHINFO_EXTENSION);
        $stem = '' === $extension ? $name : u($name)->slice(0, -(u($extension)->length() + 1))->toString();
        $suffix = '-'.u($fileId)->slice(0, 8)->toString();
        $candidate = $stem.$suffix.('' === $extension ? '' : '.'.$extension);
        $usedNames[u($candidate)->lower()->toString()] = true;

        return $candidate;
    }

    /** @param list<string> $currentPaths */
    private function removeObsoleteFiles(string $directory, array $currentPaths): void
    {
        $keep = array_fill_keys($currentPaths, true);
        foreach (glob($directory.'/{,.}*', \GLOB_BRACE) ?: [] as $path) {
            if (in_array(basename($path), ['.', '..'], true) || isset($keep[$path])) {
                continue;
            }
            if (is_file($path) || is_link($path)) {
                unlink($path);
            }
        }
    }
}
