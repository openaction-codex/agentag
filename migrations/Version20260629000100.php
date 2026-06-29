<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260629000100 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Store per-session workspace paths.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE chat_session ADD workspace_path TEXT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE chat_session DROP workspace_path');
    }
}
