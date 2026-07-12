<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260712000500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Align indexes and post-backfill defaults with current Doctrine mappings.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('DROP INDEX uniq_agent_run_source_event');
        foreach (['artifacts', 'workspace_cleanup_state', 'completed_stages', 'attempt', 'max_retries', 'retry_delay_seconds', 'notification_preference'] as $column) {
            $this->addSql(sprintf('ALTER TABLE agent_run ALTER %s DROP DEFAULT', $column));
        }
        $this->addSql('ALTER INDEX idx_agent_run_session_id RENAME TO IDX_AC4401AA613FECDF');
        $this->addSql('ALTER INDEX idx_run_event_run_id RENAME TO IDX_731102984E3FEC4');
        $this->addSql('DROP INDEX idx_messenger_messages_queue_name');
        $this->addSql('DROP INDEX idx_messenger_messages_available_at');
        $this->addSql('DROP INDEX idx_messenger_messages_delivered_at');
        $this->addSql('CREATE INDEX IDX_75EA56E0FB7336F0E3BD61CE16BA31DBBF396750 ON messenger_messages (queue_name, available_at, delivered_at, id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('CREATE UNIQUE INDEX uniq_agent_run_source_event ON agent_run (source_event_id) WHERE source_event_id IS NOT NULL');
        $this->addSql("ALTER TABLE agent_run ALTER artifacts SET DEFAULT '[]'");
        $this->addSql("ALTER TABLE agent_run ALTER workspace_cleanup_state SET DEFAULT 'retained'");
        $this->addSql("ALTER TABLE agent_run ALTER completed_stages SET DEFAULT '[]'");
        $this->addSql('ALTER TABLE agent_run ALTER attempt SET DEFAULT 0');
        $this->addSql('ALTER TABLE agent_run ALTER max_retries SET DEFAULT 2');
        $this->addSql('ALTER TABLE agent_run ALTER retry_delay_seconds SET DEFAULT 60');
        $this->addSql("ALTER TABLE agent_run ALTER notification_preference SET DEFAULT 'milestones'");
        $this->addSql('ALTER INDEX IDX_AC4401AA613FECDF RENAME TO idx_agent_run_session_id');
        $this->addSql('ALTER INDEX IDX_731102984E3FEC4 RENAME TO idx_run_event_run_id');
        $this->addSql('DROP INDEX IDX_75EA56E0FB7336F0E3BD61CE16BA31DBBF396750');
        $this->addSql('CREATE INDEX idx_messenger_messages_queue_name ON messenger_messages (queue_name)');
        $this->addSql('CREATE INDEX idx_messenger_messages_available_at ON messenger_messages (available_at)');
        $this->addSql('CREATE INDEX idx_messenger_messages_delivered_at ON messenger_messages (delivered_at)');
    }
}
