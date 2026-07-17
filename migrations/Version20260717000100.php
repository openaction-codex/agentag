<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260717000100 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Persist the first model route on each Mattermost session and reuse it for follow-up runs.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE chat_session ADD model_route VARCHAR(32) DEFAULT NULL');
        $this->addSql('ALTER TABLE chat_session ADD model_selection_reason TEXT DEFAULT NULL');
        $this->addSql(<<<'SQL'
            UPDATE chat_session
            SET model_route = first_run.model_route,
                model_selection_reason = first_run.model_selection_reason
            FROM (
                SELECT DISTINCT ON (session_id)
                    session_id,
                    model_route,
                    model_selection_reason
                FROM agent_run
                WHERE model_route IS NOT NULL
                  AND model_selection_reason IS NOT NULL
                ORDER BY session_id, id ASC
            ) AS first_run
            WHERE chat_session.id = first_run.session_id
            SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE chat_session DROP model_route');
        $this->addSql('ALTER TABLE chat_session DROP model_selection_reason');
    }
}
