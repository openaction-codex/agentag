<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260628222000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add run code environment retention metadata.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE agent_run ADD repository_base_refs JSON NOT NULL DEFAULT \'[]\'');
        $this->addSql('ALTER TABLE agent_run ADD repository_branches JSON NOT NULL DEFAULT \'[]\'');
        $this->addSql('ALTER TABLE agent_run ADD workspace_cleanup_state VARCHAR(32) NOT NULL DEFAULT \'retained\'');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE agent_run DROP repository_base_refs');
        $this->addSql('ALTER TABLE agent_run DROP repository_branches');
        $this->addSql('ALTER TABLE agent_run DROP workspace_cleanup_state');
    }
}
