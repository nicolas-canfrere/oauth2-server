<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251002054209 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create oauth_scopes table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('
            CREATE TABLE IF NOT EXISTS oauth_scopes (
                id UUID PRIMARY KEY,
                scope VARCHAR(255) NOT NULL,
                description TEXT,
                is_default BOOLEAN DEFAULT false,
                created_at TIMESTAMP NOT NULL
            )
        ');

        $this->addSql('CREATE UNIQUE INDEX IF NOT EXISTS idx_oauth_scopes_scope ON oauth_scopes (scope)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX IF EXISTS idx_oauth_scopes_scope');
        $this->addSql('DROP TABLE IF EXISTS oauth_scopes');
    }
}
