<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260719000300 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Persist Mattermost source post IDs whose attachments are task input files.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE agent_run ADD input_post_ids JSON NOT NULL DEFAULT '[]'");
        $this->addSql("UPDATE agent_run SET input_post_ids = json_build_array(source_event_id) WHERE source_event_id IS NOT NULL AND source_event_id <> ''");
        $this->addSql('ALTER TABLE agent_run ALTER input_post_ids DROP DEFAULT');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE agent_run DROP input_post_ids');
    }
}
