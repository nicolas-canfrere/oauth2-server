<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251002053924 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create oauth_token_blacklist table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('
            CREATE TABLE IF NOT EXISTS oauth_token_blacklist (
                id UUID PRIMARY KEY,
                jti VARCHAR(255) NOT NULL,
                reason TEXT,
                is_revoked BOOLEAN DEFAULT true,
                expires_at TIMESTAMP NOT NULL,
                revoked_at TIMESTAMP NOT NULL
            )
        ');

        $this->addSql('CREATE UNIQUE INDEX IF NOT EXISTS idx_oauth_token_blacklist_jti ON oauth_token_blacklist (jti)');
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_oauth_token_blacklist_expires_at ON oauth_token_blacklist (expires_at)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX IF EXISTS idx_oauth_token_blacklist_expires_at');
        $this->addSql('DROP INDEX IF EXISTS idx_oauth_token_blacklist_jti');
        $this->addSql('DROP TABLE IF EXISTS oauth_token_blacklist');
    }
}
