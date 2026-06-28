<?php

namespace App\AgentTag\Configuration;

final readonly class ConfiguredRepository
{
    private function __construct(
        private string $url,
        private string $identifier,
        private string $displayName,
    ) {
    }

    public static function fromSshUrl(string $url): self
    {
        $url = trim($url);

        if ('' === $url) {
            throw new \InvalidArgumentException('Repository URL cannot be empty.');
        }

        $path = self::extractPath($url);
        $path = preg_replace('/\.git$/', '', $path) ?? $path;
        $segments = array_values(array_filter(explode('/', $path), static fn (string $segment): bool => '' !== $segment));

        if ([] === $segments) {
            throw new \InvalidArgumentException(sprintf('Repository URL "%s" does not contain a repository path.', $url));
        }

        $displayName = $segments[array_key_last($segments)];
        $identifier = strtolower(implode('-', $segments));
        $identifier = preg_replace('/[^a-z0-9._-]+/', '-', $identifier) ?? $identifier;
        $identifier = trim($identifier, '-');

        if ('' === $identifier) {
            throw new \InvalidArgumentException(sprintf('Repository URL "%s" does not produce a usable identifier.', $url));
        }

        return new self($url, $identifier, $displayName);
    }

    public function url(): string
    {
        return $this->url;
    }

    public function identifier(): string
    {
        return $this->identifier;
    }

    public function displayName(): string
    {
        return $this->displayName;
    }

    private static function extractPath(string $url): string
    {
        $scpLikePattern = '/^[A-Za-z0-9._-]+@[A-Za-z0-9.-]+:(?<path>[^\\s]+)$/';
        if (preg_match($scpLikePattern, $url, $matches)) {
            return $matches['path'];
        }

        $sshUrlPattern = '#^ssh://[A-Za-z0-9._-]+@[A-Za-z0-9.-]+(?::[0-9]+)?/(?<path>[^\\s]+)$#';
        if (preg_match($sshUrlPattern, $url, $matches)) {
            return $matches['path'];
        }

        throw new \InvalidArgumentException(sprintf('Repository URL "%s" must be an SSH clone URL.', $url));
    }
}
