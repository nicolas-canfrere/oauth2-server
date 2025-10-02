<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251002062402 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add optimized composite and partial indexes for OAuth2 tables';
    }

    public function up(Schema $schema): void
    {
        // Composite indexes for frequent queries
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_oauth_authorization_codes_client_user_expires
            ON oauth_authorization_codes (client_id, user_id, expires_at)');

        $this->addSql('CREATE INDEX IF NOT EXISTS idx_oauth_clients_confidential_pkce
            ON oauth_clients (is_confidential, pkce_required)');

        // Partial indexes for active records only (PostgreSQL optimization)
        // Note: We cannot use time-based WHERE clauses in partial indexes as they require IMMUTABLE functions
        // Instead, we create partial indexes on boolean flags only
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_oauth_refresh_tokens_active
            ON oauth_refresh_tokens (user_id, client_id, expires_at)
            WHERE is_revoked = false');

        $this->addSql('CREATE INDEX IF NOT EXISTS idx_oauth_keys_active
            ON oauth_keys (kid, expires_at)
            WHERE is_active = true');
    }

    public function down(Schema $schema): void
    {
        // Drop partial indexes
        $this->addSql('DROP INDEX IF EXISTS idx_oauth_keys_active');
        $this->addSql('DROP INDEX IF EXISTS idx_oauth_refresh_tokens_active');

        // Drop composite indexes
        $this->addSql('DROP INDEX IF EXISTS idx_oauth_clients_confidential_pkce');
        $this->addSql('DROP INDEX IF EXISTS idx_oauth_authorization_codes_client_user_expires');
    }
}
