<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260713000200 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Persist the intake model route and its user-visible rationale on each agent run.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE agent_run ADD model_route VARCHAR(32) DEFAULT NULL');
        $this->addSql('ALTER TABLE agent_run ADD model_selection_reason TEXT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE agent_run DROP model_route');
        $this->addSql('ALTER TABLE agent_run DROP model_selection_reason');
    }
}
