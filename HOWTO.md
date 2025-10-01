# How-To Guide

## Doctrine Migration Generator

This project includes a custom Claude Code slash command for generating Doctrine migrations with automatic PostgreSQL SQL generation.

### Quick Start

```bash
/doctrine-migration create <table_name> <column_definitions>
```

### Column Definition Syntax

**Format**: `name:type:constraints`

**Example**:
```bash
/doctrine-migration create users \
  id:uuid:primary \
  email:string:unique:notnull \
  created_at:timestamp:notnull
```

---

## Supported Types

| Type | PostgreSQL | Description |
|------|------------|-------------|
| `uuid` | UUID | Universally unique identifier |
| `string` | VARCHAR(255) | Variable character (default 255) |
| `string(n)` | VARCHAR(n) | Custom length string |
| `text` | TEXT | Unlimited text |
| `integer` | INTEGER | 4-byte integer |
| `bigint` | BIGINT | 8-byte integer |
| `boolean` | BOOLEAN | True/false |
| `timestamp` | TIMESTAMP | Date and time |
| `timestamptz` | TIMESTAMP WITH TIME ZONE | Timestamp with timezone |
| `date` | DATE | Date only |
| `jsonb` | JSONB | Binary JSON (efficient) |
| `decimal(p,s)` | DECIMAL(p,s) | Decimal with precision/scale |

---

## Supported Constraints

| Constraint | SQL | Description |
|------------|-----|-------------|
| `primary` | PRIMARY KEY | Primary key constraint |
| `unique` | UNIQUE | Unique value constraint |
| `notnull` | NOT NULL | Required field |
| `nullable` | NULL | Optional field (default) |
| `default(value)` | DEFAULT value | Default value |

---

## Common Usage Examples

### 1. Create Simple Table

```bash
/doctrine-migration create users \
  id:uuid:primary \
  email:string:unique:notnull \
  username:string:notnull \
  created_at:timestamp:notnull
```

### 2. Table with JSONB and Booleans

```bash
/doctrine-migration create oauth_clients \
  id:uuid:primary \
  client_id:string:unique:notnull \
  client_secret_hash:string:notnull \
  name:string:notnull \
  redirect_uris:jsonb \
  grant_types:jsonb \
  is_confidential:boolean:default(true) \
  created_at:timestamp:notnull
```

### 3. Add Indexes

```bash
/doctrine-migration create sessions \
  id:uuid:primary \
  token:string:notnull \
  user_id:uuid:notnull \
  expires_at:timestamp:notnull \
  --index token:unique \
  --index expires_at
```

### 4. Foreign Keys

```bash
/doctrine-migration create posts \
  id:uuid:primary \
  user_id:uuid:notnull \
  title:string:notnull \
  content:text \
  created_at:timestamp:notnull \
  --foreign-key user_id:users(id):cascade
```

**Foreign Key Format**: `column:reference_table(reference_column):on_delete_action`

**Available Actions**:
- `cascade` - Delete child records when parent is deleted
- `set_null` - Set foreign key to NULL when parent is deleted
- `restrict` - Prevent deletion if child records exist
- `no_action` - No action (database default)

### 5. Complex Table with All Features

```bash
/doctrine-migration create oauth_tokens \
  id:uuid:primary \
  client_id:uuid:notnull \
  user_id:uuid \
  access_token:string(500):unique:notnull \
  refresh_token:string(500):unique \
  expires_at:timestamp:notnull \
  scope:jsonb \
  revoked:boolean:default(false) \
  created_at:timestamp:notnull \
  --foreign-key client_id:oauth_clients(id):cascade \
  --foreign-key user_id:users(id):set_null \
  --index expires_at \
  --index revoked
```

---

## Alter Table Operations

### Add Column

```bash
/doctrine-migration alter users --add phone:string
```

### Drop Column

```bash
/doctrine-migration alter users --drop phone
```

### Modify Column

```bash
/doctrine-migration alter users --modify email:string(100):unique
```

---

## Drop Table

```bash
/doctrine-migration drop <table_name>
```

**Example**:
```bash
/doctrine-migration drop old_sessions
```

---

## What Happens Behind the Scenes

1. **Command Parsing**: Extracts operation, table name, columns, constraints
2. **Validation**: Verifies syntax and supported types
3. **SQL Generation**: Creates PostgreSQL SQL for `up()` and `down()` methods
4. **File Creation**: Executes `make migrations-generate` to create empty migration
5. **SQL Injection**: Injects generated SQL into migration class
6. **Validation**: Runs PHPStan to validate PHP syntax
7. **Optional Execution**: If `--execute` flag used, runs `make migrations-migrate`

---

## Generated Migration Structure

```php
<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20251001193223 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create oauth_authorization_codes table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('
            CREATE TABLE IF NOT EXISTS oauth_authorization_codes (
                id UUID PRIMARY KEY,
                code VARCHAR(255) NOT NULL,
                client_id VARCHAR(255) NOT NULL,
                expires_at TIMESTAMP NOT NULL
            )
        ');

        $this->addSql('
            CREATE UNIQUE INDEX IF NOT EXISTS idx_oauth_authorization_codes_code
            ON oauth_authorization_codes (code)
        ');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS oauth_authorization_codes CASCADE');
    }
}
```

---

## Best Practices

### ✅ Do

- **Review SQL**: Always review generated SQL before executing
- **Use UUIDs**: For primary keys in distributed systems
- **Use JSONB**: For flexible/dynamic data (scopes, metadata, URIs)
- **Add Indexes**: On frequently queried columns (foreign keys, timestamps, unique codes)
- **Use Timestamps**: Track record creation and updates
- **Idempotent Migrations**: Generated SQL uses `IF NOT EXISTS`/`IF EXISTS` for safety

### ❌ Don't

- **Don't auto-execute**: Avoid `--execute` flag until you've reviewed the migration
- **Don't skip foreign keys**: Define relationships properly for referential integrity
- **Don't forget indexes**: Performance degrades without proper indexing
- **Don't use nullable FKs**: Unless business logic explicitly requires it

---

## Workflow Integration

### Standard Development Flow

```bash
# 1. Generate migration
/doctrine-migration create my_table id:uuid:primary name:string

# 2. Review generated file
cat migrations/Version*.php

# 3. Execute migration
make migrations-migrate

# 4. Verify in database
make php-cli
# Inside container:
bin/console doctrine:migrations:status
```

### Batch Migrations

```bash
# Generate multiple related tables
/doctrine-migration create users id:uuid:primary email:string:unique
/doctrine-migration create posts id:uuid:primary user_id:uuid --foreign-key user_id:users(id)
/doctrine-migration create comments id:uuid:primary post_id:uuid --foreign-key post_id:posts(id)

# Execute all at once
make migrations-migrate
```

---

## Troubleshooting

### Validation Errors

If Claude reports validation errors:

1. **Check type spelling**: `uuid`, `string`, `integer`, etc.
2. **Verify constraint syntax**: `primary`, `unique`, `notnull`
3. **Foreign key format**: `column:table(column):action`
4. **Review error message**: Claude will suggest corrections

### Migration Already Exists

```bash
# If table already exists, use alter instead
/doctrine-migration alter existing_table --add new_column:string
```

### Need to Rollback

```bash
# Execute down() migration
docker compose run --rm php bin/console doctrine:migrations:execute --down 'DoctrineMigrations\Version20251001193223'
```

---

## Advanced Examples

### OAuth2 Complete Schema

```bash
# Clients
/doctrine-migration create oauth_clients \
  id:uuid:primary \
  client_id:string:unique:notnull \
  client_secret_hash:string:notnull \
  name:string:notnull \
  redirect_uris:jsonb \
  grant_types:jsonb \
  scopes:jsonb \
  is_confidential:boolean:default(true) \
  created_at:timestamp:notnull

# Authorization Codes
/doctrine-migration create oauth_authorization_codes \
  id:uuid:primary \
  code:string:unique:notnull \
  client_id:string:notnull \
  user_id:string:notnull \
  redirect_uri:string:notnull \
  scopes:jsonb \
  code_challenge:string \
  code_challenge_method:string \
  expires_at:timestamp:notnull \
  created_at:timestamp:notnull \
  --index expires_at

# Access Tokens
/doctrine-migration create oauth_access_tokens \
  id:uuid:primary \
  client_id:uuid:notnull \
  user_id:uuid \
  token:string(500):unique:notnull \
  expires_at:timestamp:notnull \
  scopes:jsonb \
  revoked:boolean:default(false) \
  created_at:timestamp:notnull \
  --foreign-key client_id:oauth_clients(id):cascade \
  --foreign-key user_id:users(id):set_null \
  --index expires_at \
  --index revoked
```

---

## Notes

- All generated SQL is **idempotent** (safe to run multiple times)
- Migrations use **IF NOT EXISTS** and **IF EXISTS** clauses
- PHPStan validation ensures PHP syntax correctness
- Follows project coding standards (PSR-12, strict types)
- Compatible with Docker environment
- Uses `make` commands for consistency