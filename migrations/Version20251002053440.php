<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251002053440 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create oauth_refresh_tokens table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('
            CREATE TABLE IF NOT EXISTS oauth_refresh_tokens (
                id UUID PRIMARY KEY,
                token VARCHAR(255) NOT NULL,
                client_id VARCHAR(255) NOT NULL,
                user_id VARCHAR(255) NOT NULL,
                scopes JSONB,
                is_revoked BOOLEAN DEFAULT false,
                expires_at TIMESTAMP NOT NULL,
                created_at TIMESTAMP NOT NULL
            )
        ');

        $this->addSql('CREATE UNIQUE INDEX IF NOT EXISTS idx_oauth_refresh_tokens_token ON oauth_refresh_tokens (token)');
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_oauth_refresh_tokens_client_user_revoked ON oauth_refresh_tokens (client_id, user_id, is_revoked)');
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_oauth_refresh_tokens_expires_at ON oauth_refresh_tokens (expires_at)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX IF EXISTS idx_oauth_refresh_tokens_expires_at');
        $this->addSql('DROP INDEX IF EXISTS idx_oauth_refresh_tokens_client_user_revoked');
        $this->addSql('DROP INDEX IF EXISTS idx_oauth_refresh_tokens_token');
        $this->addSql('DROP TABLE IF EXISTS oauth_refresh_tokens');
    }
}
