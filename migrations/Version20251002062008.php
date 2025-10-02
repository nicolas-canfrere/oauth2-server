<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251002062008 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create oauth_keys table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE IF NOT EXISTS oauth_keys (
            id UUID PRIMARY KEY,
            kid VARCHAR(255) NOT NULL,
            algorithm VARCHAR(255) NOT NULL,
            public_key TEXT NOT NULL,
            private_key_encrypted TEXT NOT NULL,
            is_active BOOLEAN DEFAULT false,
            created_at TIMESTAMP NOT NULL,
            expires_at TIMESTAMP NOT NULL
        )');

        $this->addSql('CREATE UNIQUE INDEX IF NOT EXISTS idx_oauth_keys_kid ON oauth_keys (kid)');
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_oauth_keys_active_created ON oauth_keys (is_active, created_at)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX IF EXISTS idx_oauth_keys_active_created');
        $this->addSql('DROP INDEX IF EXISTS idx_oauth_keys_kid');
        $this->addSql('DROP TABLE IF EXISTS oauth_keys');
    }
}
