<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260628212200 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Record runner outputs and workspace metadata on agent runs.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE agent_run ADD workspace_path TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE agent_run ADD artifacts JSON NOT NULL DEFAULT \'[]\'');
        $this->addSql('ALTER TABLE agent_run ADD log_summary TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE agent_run ADD exit_code INT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE agent_run DROP workspace_path');
        $this->addSql('ALTER TABLE agent_run DROP artifacts');
        $this->addSql('ALTER TABLE agent_run DROP log_summary');
        $this->addSql('ALTER TABLE agent_run DROP exit_code');
    }
}
