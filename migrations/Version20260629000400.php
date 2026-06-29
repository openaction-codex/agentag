<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260629000400 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Remove obsolete repository clone metadata from runs.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE agent_run DROP repository_clones');
        $this->addSql('ALTER TABLE agent_run DROP repository_base_refs');
        $this->addSql('ALTER TABLE agent_run DROP repository_branches');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE agent_run ADD repository_clones JSON NOT NULL DEFAULT \'[]\'');
        $this->addSql('ALTER TABLE agent_run ADD repository_base_refs JSON NOT NULL DEFAULT \'[]\'');
        $this->addSql('ALTER TABLE agent_run ADD repository_branches JSON NOT NULL DEFAULT \'[]\'');
    }
}
