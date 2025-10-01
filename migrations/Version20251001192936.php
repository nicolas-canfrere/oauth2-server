<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251001192936 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create oauth_clients table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE EXTENSION IF NOT EXISTS "uuid-ossp"');
        $this->addSql('
            CREATE TABLE IF NOT EXISTS oauth_clients (
                id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
                client_id VARCHAR(255) NOT NULL,
                client_secret_hash VARCHAR(255) NOT NULL,
                name VARCHAR(255) NOT NULL,
                redirect_uri VARCHAR(255) NOT NULL,
                grant_types JSONB NOT NULL DEFAULT \'[]\',
                scopes JSONB NOT NULL DEFAULT \'[]\',
                is_confidential BOOLEAN DEFAULT true,
                pkce_required BOOLEAN DEFAULT false,
                created_at TIMESTAMP NOT NULL,
                updated_at TIMESTAMP NOT NULL
            )
        ');
        $this->addSql('CREATE UNIQUE INDEX IF NOT EXISTS idx_oauth_clients_client_id ON oauth_clients (client_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX IF EXISTS idx_oauth_clients_client_id');
        $this->addSql('DROP TABLE IF EXISTS oauth_clients');
    }
}
