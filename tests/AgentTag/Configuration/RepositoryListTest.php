<?php

namespace App\Tests\AgentTag\Configuration;

use App\AgentTag\Configuration\RepositoryList;
use PHPUnit\Framework\TestCase;

final class RepositoryListTest extends TestCase
{
    public function testItParsesCommaSeparatedSshUrls(): void
    {
        $repositories = RepositoryList::fromCsv(
            'git@github.com:openaction-codex/agentag.git, ssh://git@github.com/example-org/api.git',
        );

        $items = iterator_to_array($repositories);

        self::assertCount(2, $repositories);
        self::assertSame('openaction-codex-agentag', $items[0]->identifier());
        self::assertSame('agentag', $items[0]->displayName());
        self::assertSame('git@github.com:openaction-codex/agentag.git', $items[0]->url());
        self::assertSame('example-org-api', $items[1]->identifier());
    }

    public function testItRejectsNonSshUrls(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('must be an SSH clone URL');

        RepositoryList::fromCsv('https://github.com/openaction-codex/agentag.git');
    }

    public function testItRejectsAmbiguousIdentifiers(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('is ambiguous');

        RepositoryList::fromCsv(
            'git@github.com:openaction-codex/agentag.git,git@github.com:openaction-codex/agentag.git',
        );
    }
}
