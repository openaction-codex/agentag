<?php

namespace App\AgentTag\Workspace;

final readonly class WorkspaceTemplateCopier
{
    public function copy(string $sourcePath, string $targetPath): void
    {
        if (!is_dir($sourcePath)) {
            throw new \RuntimeException(sprintf('Workspace template directory "%s" does not exist.', $sourcePath));
        }

        if (file_exists($targetPath)) {
            return;
        }

        $parent = \dirname($targetPath);
        if (!is_dir($parent)) {
            mkdir($parent, 0777, true);
        }

        mkdir($targetPath, 0777, true);
        $this->copyDirectory(rtrim($sourcePath, '/'), rtrim($targetPath, '/'));
    }

    private function copyDirectory(string $sourcePath, string $targetPath): void
    {
        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($sourcePath, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST,
        );

        foreach ($files as $file) {
            if (!$file instanceof \SplFileInfo) {
                continue;
            }

            $source = $file->getPathname();
            $relativePath = substr($source, strlen($sourcePath) + 1);
            if ('.git' === $relativePath || str_starts_with($relativePath, '.git/')) {
                continue;
            }

            $target = $targetPath.'/'.$relativePath;

            if ($file->isLink()) {
                symlink((string) readlink($source), $target);
                continue;
            }

            if ($file->isDir()) {
                if (!is_dir($target)) {
                    mkdir($target, 0777, true);
                }
                continue;
            }

            $targetParent = \dirname($target);
            if (!is_dir($targetParent)) {
                mkdir($targetParent, 0777, true);
            }

            copy($source, $target);
        }
    }
}
