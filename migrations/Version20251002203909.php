<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Rename token/code columns to token_hash/code_hash for security.
 *
 * BREAKING CHANGE: This migration will fail if the database contains
 * existing tokens/codes, as they cannot be hashed retroactively.
 * All tokens must be re-issued after this migration.
 */
final class Version20251002203909 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Rename plaintext token columns to hashed columns for OAuth2 security.';
    }

    public function up(Schema $schema): void
    {
        // Check if tables are empty (safer migration)
        $this->addSql('DO $$
        BEGIN
            IF EXISTS (SELECT 1 FROM oauth_refresh_tokens LIMIT 1) THEN
                RAISE EXCEPTION \'Cannot migrate: oauth_refresh_tokens contains data. All tokens must be revoked and re-issued.\';
            END IF;
            IF EXISTS (SELECT 1 FROM oauth_authorization_codes LIMIT 1) THEN
                RAISE EXCEPTION \'Cannot migrate: oauth_authorization_codes contains data. Codes must expire before migration.\';
            END IF;
        END $$;');

        // Rename oauth_refresh_tokens.token → token_hash
        $this->addSql('ALTER TABLE oauth_refresh_tokens RENAME COLUMN token TO token_hash');
        $this->addSql('DROP INDEX IF EXISTS idx_oauth_refresh_tokens_token');
        $this->addSql('CREATE UNIQUE INDEX idx_oauth_refresh_tokens_token_hash ON oauth_refresh_tokens (token_hash)');

        // Rename oauth_authorization_codes.code → code_hash
        $this->addSql('ALTER TABLE oauth_authorization_codes RENAME COLUMN code TO code_hash');
        $this->addSql('DROP INDEX IF EXISTS idx_oauth_authorization_codes_code');
        $this->addSql('CREATE UNIQUE INDEX idx_oauth_authorization_codes_code_hash ON oauth_authorization_codes (code_hash)');
    }

    public function down(Schema $schema): void
    {
        // Rollback: code_hash → code
        $this->addSql('ALTER TABLE oauth_authorization_codes RENAME COLUMN code_hash TO code');
        $this->addSql('DROP INDEX IF EXISTS idx_oauth_authorization_codes_code_hash');
        $this->addSql('CREATE UNIQUE INDEX idx_oauth_authorization_codes_code ON oauth_authorization_codes (code)');

        // Rollback: token_hash → token
        $this->addSql('ALTER TABLE oauth_refresh_tokens RENAME COLUMN token_hash TO token');
        $this->addSql('DROP INDEX IF EXISTS idx_oauth_refresh_tokens_token_hash');
        $this->addSql('CREATE UNIQUE INDEX idx_oauth_refresh_tokens_token ON oauth_refresh_tokens (token)');
    }
}
