<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260628211200 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Record selected workflow metadata on agent runs.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE agent_run ADD workflow_name VARCHAR(120) DEFAULT NULL');
        $this->addSql('ALTER TABLE agent_run ADD workflow_version VARCHAR(120) DEFAULT NULL');
        $this->addSql('ALTER TABLE agent_run ADD workflow_revision VARCHAR(120) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE agent_run DROP workflow_name');
        $this->addSql('ALTER TABLE agent_run DROP workflow_version');
        $this->addSql('ALTER TABLE agent_run DROP workflow_revision');
    }
}
