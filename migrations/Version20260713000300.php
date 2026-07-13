<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260713000300 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Persist verified Codex subagent launch metadata for the Mattermost task card.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE agent_run ADD subagent_thread_id VARCHAR(64) DEFAULT NULL');
        $this->addSql('ALTER TABLE agent_run ADD subagent_agent VARCHAR(64) DEFAULT NULL');
        $this->addSql('ALTER TABLE agent_run ADD subagent_model VARCHAR(64) DEFAULT NULL');
        $this->addSql('ALTER TABLE agent_run ADD subagent_reasoning_effort VARCHAR(16) DEFAULT NULL');
        $this->addSql('ALTER TABLE agent_run ADD subagent_metadata_verified BOOLEAN DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE agent_run DROP subagent_thread_id');
        $this->addSql('ALTER TABLE agent_run DROP subagent_agent');
        $this->addSql('ALTER TABLE agent_run DROP subagent_model');
        $this->addSql('ALTER TABLE agent_run DROP subagent_reasoning_effort');
        $this->addSql('ALTER TABLE agent_run DROP subagent_metadata_verified');
    }
}
