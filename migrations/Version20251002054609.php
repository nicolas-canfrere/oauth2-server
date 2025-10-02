<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251002054609 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create users table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('
            CREATE TABLE IF NOT EXISTS users (
                id UUID PRIMARY KEY,
                email VARCHAR(255) NOT NULL,
                password_hash VARCHAR(255) NOT NULL,
                totp_secret VARCHAR(255),
                is_2fa_enabled BOOLEAN DEFAULT false,
                updated_at TIMESTAMP NOT NULL,
                created_at TIMESTAMP NOT NULL
            )
        ');

        $this->addSql('CREATE UNIQUE INDEX IF NOT EXISTS idx_users_email ON users (email)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX IF EXISTS idx_users_email');
        $this->addSql('DROP TABLE IF EXISTS users');
    }
}
