<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Create oauth_audit_logs table for security audit trail.
 *
 * Stores all security-relevant events: authentication, token issuance,
 * token revocation, client management, rate limiting, and suspicious activities.
 */
final class Version20251004065214 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create oauth_audit_logs table for OAuth2 security audit trail';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('
            CREATE TABLE IF NOT EXISTS oauth_audit_logs (
                id UUID PRIMARY KEY,
                event_type VARCHAR(100) NOT NULL,
                level VARCHAR(20) NOT NULL,
                message TEXT NOT NULL,
                context JSONB DEFAULT \'{}\'::jsonb,
                user_id UUID,
                client_id VARCHAR(255),
                ip_address VARCHAR(45),
                user_agent TEXT,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
            )
        ');

        // Performance indexes for common queries
        $this->addSql('CREATE INDEX idx_audit_logs_event_type ON oauth_audit_logs (event_type)');
        $this->addSql('CREATE INDEX idx_audit_logs_user_id ON oauth_audit_logs (user_id)');
        $this->addSql('CREATE INDEX idx_audit_logs_client_id ON oauth_audit_logs (client_id)');
        $this->addSql('CREATE INDEX idx_audit_logs_created_at ON oauth_audit_logs (created_at DESC)');
        $this->addSql('CREATE INDEX idx_audit_logs_level ON oauth_audit_logs (level)');

        // Composite index for user security timeline
        $this->addSql('CREATE INDEX idx_audit_logs_user_timeline ON oauth_audit_logs (user_id, created_at DESC)');

        // Composite index for client activity monitoring
        $this->addSql('CREATE INDEX idx_audit_logs_client_activity ON oauth_audit_logs (client_id, created_at DESC)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS oauth_audit_logs CASCADE');
    }
}
