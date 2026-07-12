<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260712000300 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Persist inbound event deduplication across PHP processes and restarts.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE inbound_event (event_id VARCHAR(255) NOT NULL, received_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(event_id))');
        $this->addSql('CREATE INDEX idx_inbound_event_received_at ON inbound_event (received_at)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE inbound_event');
    }
}
