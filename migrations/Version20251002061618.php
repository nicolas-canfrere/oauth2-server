<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251002061618 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create user_consents table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE IF NOT EXISTS user_consents (
            id UUID PRIMARY KEY,
            user_id VARCHAR(255) NOT NULL,
            client_id VARCHAR(255) NOT NULL,
            scopes JSONB NOT NULL DEFAULT \'[]\',
            granted_at TIMESTAMP NOT NULL,
            expires_at TIMESTAMP NOT NULL
        )');

        $this->addSql('CREATE UNIQUE INDEX IF NOT EXISTS idx_user_consents_user_client ON user_consents (user_id, client_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX IF EXISTS idx_user_consents_user_client');
        $this->addSql('DROP TABLE IF EXISTS user_consents');
    }
}
