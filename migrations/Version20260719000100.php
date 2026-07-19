<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260719000100 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Persist Mattermost file IDs so reply attachment delivery is idempotent across worker retries.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE agent_run ADD mattermost_file_ids JSON NOT NULL DEFAULT '[]'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE agent_run DROP mattermost_file_ids');
    }
}
