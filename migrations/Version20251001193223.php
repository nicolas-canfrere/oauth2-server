<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251001193223 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create oauth_authorization_codes table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('
            CREATE TABLE IF NOT EXISTS oauth_authorization_codes (
                id UUID PRIMARY KEY,
                code VARCHAR(255) NOT NULL,
                client_id VARCHAR(255) NOT NULL,
                user_id VARCHAR(255) NOT NULL,
                redirect_uri VARCHAR(255) NOT NULL,
                scopes JSONB NOT NULL DEFAULT \'[]\',
                code_challenge VARCHAR(255),
                code_challenge_method VARCHAR(255),
                expires_at TIMESTAMP NOT NULL,
                created_at TIMESTAMP NOT NULL
            )
        ');

        $this->addSql('
            CREATE UNIQUE INDEX IF NOT EXISTS idx_oauth_authorization_codes_code
            ON oauth_authorization_codes (code)
        ');

        $this->addSql('
            CREATE INDEX IF NOT EXISTS idx_oauth_authorization_codes_expires_at
            ON oauth_authorization_codes (expires_at)
        ');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS oauth_authorization_codes CASCADE');
    }
}
