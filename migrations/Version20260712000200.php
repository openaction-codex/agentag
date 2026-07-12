<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260712000200 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add durable task cards, resumability, scheduling, retries, deadlines, and notification preferences.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE agent_run ADD title VARCHAR(160) DEFAULT NULL');
        $this->addSql('ALTER TABLE agent_run ADD acknowledgement TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE agent_run ADD task_post_id VARCHAR(64) DEFAULT NULL');
        $this->addSql('ALTER TABLE agent_run ADD requester_name VARCHAR(120) DEFAULT NULL');
        $this->addSql('ALTER TABLE agent_run ADD started_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('ALTER TABLE agent_run ADD finished_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('ALTER TABLE agent_run ADD codex_thread_id VARCHAR(64) DEFAULT NULL');
        $this->addSql('ALTER TABLE agent_run ADD current_stage TEXT DEFAULT NULL');
        $this->addSql("ALTER TABLE agent_run ADD completed_stages JSON NOT NULL DEFAULT '[]'");
        $this->addSql('ALTER TABLE agent_run ADD pending_steering TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE agent_run ADD interruption_kind VARCHAR(16) DEFAULT NULL');
        $this->addSql('ALTER TABLE agent_run ADD retained_until TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('ALTER TABLE agent_run ADD wake_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('ALTER TABLE agent_run ADD wait_reason TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE agent_run ADD deadline_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('ALTER TABLE agent_run ADD attempt INT NOT NULL DEFAULT 0');
        $this->addSql('ALTER TABLE agent_run ADD max_retries INT NOT NULL DEFAULT 2');
        $this->addSql('ALTER TABLE agent_run ADD retry_delay_seconds INT NOT NULL DEFAULT 60');
        $this->addSql("ALTER TABLE agent_run ADD notification_preference VARCHAR(24) NOT NULL DEFAULT 'milestones'");
        $this->addSql('CREATE UNIQUE INDEX uniq_agent_run_source_event ON agent_run (source_event_id) WHERE source_event_id IS NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX uniq_agent_run_source_event');
        foreach (['title', 'acknowledgement', 'task_post_id', 'requester_name', 'started_at', 'finished_at', 'codex_thread_id', 'current_stage', 'completed_stages', 'pending_steering', 'interruption_kind', 'retained_until', 'wake_at', 'wait_reason', 'deadline_at', 'attempt', 'max_retries', 'retry_delay_seconds', 'notification_preference'] as $column) {
            $this->addSql(sprintf('ALTER TABLE agent_run DROP %s', $column));
        }
    }
}
