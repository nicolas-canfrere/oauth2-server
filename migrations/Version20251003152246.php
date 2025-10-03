<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251003152246 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add roles column to users table for Symfony Security integration';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            ALTER TABLE users
            ADD COLUMN IF NOT EXISTS roles JSONB NOT NULL DEFAULT '["ROLE_USER"]'::jsonb
        SQL);

        $this->addSql(<<<'SQL'
            COMMENT ON COLUMN users.roles IS 'User roles for Symfony Security (JSONB array)'
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            ALTER TABLE users
            DROP COLUMN IF EXISTS roles
        SQL);
    }
}
