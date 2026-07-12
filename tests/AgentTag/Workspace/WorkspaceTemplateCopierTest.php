<?php

namespace App\Tests\AgentTag\Workspace;

use App\AgentTag\Workspace\WorkspaceTemplateCopier;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;

final class WorkspaceTemplateCopierTest extends TestCase
{
    private string $root;

    #[\Override]
    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir().'/agentag-copy-'.bin2hex(random_bytes(5));
        mkdir($this->root.'/source/.git', 0777, true);
        mkdir($this->root.'/source/docs', 0777, true);
        file_put_contents($this->root.'/source/AGENTS.md', 'instructions');
        file_put_contents($this->root.'/source/.hidden', 'included');
        file_put_contents($this->root.'/source/.git/config', 'excluded');
        file_put_contents($this->root.'/source/docs/readme.md', 'docs');
    }

    #[\Override]
    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->root);
    }

    public function testItMirrorsTheTemplateWithDotFilesButWithoutGitMetadata(): void
    {
        (new WorkspaceTemplateCopier(new Filesystem()))->copy($this->root.'/source', $this->root.'/target');

        self::assertFileExists($this->root.'/target/AGENTS.md');
        self::assertFileExists($this->root.'/target/.hidden');
        self::assertFileExists($this->root.'/target/docs/readme.md');
        self::assertDirectoryDoesNotExist($this->root.'/target/.git');
    }
}
