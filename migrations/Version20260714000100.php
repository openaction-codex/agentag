<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260714000100 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Run selected models directly and remove obsolete subagent session metadata.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("UPDATE agent_run SET model_route = 'sol-medium' WHERE model_route = 'terra-max'");
        $this->addSql('ALTER TABLE agent_run DROP subagent_thread_id');
        $this->addSql('ALTER TABLE agent_run DROP subagent_agent');
        $this->addSql('ALTER TABLE agent_run DROP subagent_model');
        $this->addSql('ALTER TABLE agent_run DROP subagent_reasoning_effort');
        $this->addSql('ALTER TABLE agent_run DROP subagent_metadata_verified');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE agent_run ADD subagent_thread_id VARCHAR(64) DEFAULT NULL');
        $this->addSql('ALTER TABLE agent_run ADD subagent_agent VARCHAR(64) DEFAULT NULL');
        $this->addSql('ALTER TABLE agent_run ADD subagent_model VARCHAR(64) DEFAULT NULL');
        $this->addSql('ALTER TABLE agent_run ADD subagent_reasoning_effort VARCHAR(16) DEFAULT NULL');
        $this->addSql('ALTER TABLE agent_run ADD subagent_metadata_verified BOOLEAN DEFAULT NULL');
        $this->addSql("UPDATE agent_run SET model_route = 'luna-max' WHERE model_route = 'sol-medium'");
    }
}
