<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260712000400 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Remove Slack-era platform and unused session summary columns.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE chat_session DROP platform');
        $this->addSql('ALTER TABLE chat_session DROP summary');
    }

    public function down(Schema $schema): void
    {
        $this->addSql("ALTER TABLE chat_session ADD platform VARCHAR(32) NOT NULL DEFAULT 'mattermost'");
        $this->addSql('ALTER TABLE chat_session ADD summary TEXT DEFAULT NULL');
    }
}
