<?php

namespace App\AgentTag\Workspace;

use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;

final readonly class WorkspaceTemplateCopier
{
    public function __construct(private Filesystem $filesystem)
    {
    }

    public function copy(string $sourcePath, string $targetPath): void
    {
        if (!is_dir($sourcePath)) {
            throw new \RuntimeException(sprintf('Workspace template directory "%s" does not exist.', $sourcePath));
        }
        if ($this->filesystem->exists($targetPath)) {
            return;
        }

        $files = Finder::create()
            ->in($sourcePath)
            ->ignoreDotFiles(false)
            ->exclude('.git');
        $this->filesystem->mirror($sourcePath, $targetPath, $files);
    }
}
