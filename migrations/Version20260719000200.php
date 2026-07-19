<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260719000200 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Remove the temporary Mattermost file ID backfill default to match the Doctrine mapping.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE agent_run ALTER mattermost_file_ids DROP DEFAULT');
    }

    public function down(Schema $schema): void
    {
        $this->addSql("ALTER TABLE agent_run ALTER mattermost_file_ids SET DEFAULT '[]'");
    }
}
