<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260723000100 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Record the Mattermost user who stopped an agent run.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE agent_run ADD stopped_by_name VARCHAR(120) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE agent_run DROP stopped_by_name');
    }
}
