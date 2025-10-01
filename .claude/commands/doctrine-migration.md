---
name: doctrine-migration
description: Generate Doctrine migrations with automatic SQL generation for PostgreSQL.
arguments:
  operation: create|alter|drop table operation
  table_name: Name of the table
  columns: Column definitions in format "name:type:constraints"
flags:
  - --index: Add index definition
  - --foreign-key: Add foreign key constraint
  - --execute: Execute migration after generation
examples:
  - /doctrine-migration create oauth_clients id:uuid:primary client_id:string:unique name:string redirect_uris:jsonb created_at:timestamp
  - /doctrine-migration alter users --add email:string:unique
  - /doctrine-migration create posts id:uuid:primary user_id:uuid --foreign-key user_id:users(id)
---
**1.1.1** 
# Doctrine Migration Generator

Automated Doctrine migration generation with PostgreSQL SQL generation.

## MANDATORY - NOT NEGOTIABLE

GENERATED SQL MUST BE SECURED AND IDEMPOTENT

Use IF NOT EXIST, IF EXISTS and other verifications to ensure that.

## Usage Patterns

### Create Table
```bash
/doctrine-migration create <table_name> <column_definitions>
```

**Column Definition Format**: `name:type:constraints`

**Supported Types**:
- `uuid` → UUID
- `string` → VARCHAR(255)
- `string(n)` → VARCHAR(n)
- `text` → TEXT
- `integer` → INTEGER
- `bigint` → BIGINT
- `boolean` → BOOLEAN
- `timestamp` → TIMESTAMP
- `date` → DATE
- `jsonb` → JSONB
- `decimal(p,s)` → DECIMAL(p,s)

**Supported Constraints**:
- `primary` → PRIMARY KEY
- `unique` → UNIQUE
- `notnull` → NOT NULL
- `nullable` → NULL (default)
- `default(value)` → DEFAULT value

**Examples**:
```bash
# OAuth clients table
/doctrine-migration create oauth_clients \
  id:uuid:primary \
  client_id:string:unique:notnull \
  client_secret_hash:string:notnull \
  name:string:notnull \
  redirect_uris:jsonb \
  grant_types:jsonb \
  scopes:jsonb \
  is_confidential:boolean:default(true) \
  pkce_required:boolean:default(false) \
  created_at:timestamp:notnull \
  updated_at:timestamp:notnull

# With indexes
/doctrine-migration create users \
  id:uuid:primary \
  email:string:unique \
  username:string \
  --index username
```

### Alter Table
```bash
/doctrine-migration alter <table_name> --add <column_definitions>
/doctrine-migration alter <table_name> --drop <column_name>
/doctrine-migration alter <table_name> --modify <column_definition>
```

**Examples**:
```bash
# Add column
/doctrine-migration alter users --add phone:string

# Drop column
/doctrine-migration alter users --drop phone

# Modify column
/doctrine-migration alter users --modify email:string(100):unique
```

### Drop Table
```bash
/doctrine-migration drop <table_name>
```

### Foreign Keys
```bash
/doctrine-migration create posts \
  id:uuid:primary \
  user_id:uuid:notnull \
  title:string:notnull \
  content:text \
  --foreign-key user_id:users(id):cascade
```

**Foreign Key Format**: `column:reference_table(reference_column):on_delete_action`

**On Delete Actions**: `cascade`, `set_null`, `restrict`, `no_action`

## Workflow

When you invoke this command, Claude will:

1. **Parse the command** to extract operation, table name, columns, and constraints
2. **Validate the syntax** and ensure types/constraints are supported
3. **Generate PostgreSQL SQL** for the up() method
4. **Generate rollback SQL** for the down() method
5. **Execute `make migrations-generate`** to create empty migration file
6. **Inject SQL** into the generated migration class
7. **Validate** the migration file PHP syntax
8. **Optionally execute** `make migrations-migrate` if `--execute` flag is used

## Type Mapping Reference

| Input Type | PostgreSQL Type | Notes |
|------------|----------------|-------|
| uuid | UUID | Requires uuid-ossp extension |
| string | VARCHAR(255) | Default length 255 |
| string(n) | VARCHAR(n) | Custom length |
| text | TEXT | Unlimited length |
| integer | INTEGER | 4 bytes |
| bigint | BIGINT | 8 bytes |
| boolean | BOOLEAN | true/false |
| timestamp | TIMESTAMP | Without timezone |
| timestamptz | TIMESTAMP WITH TIME ZONE | With timezone |
| date | DATE | Date only |
| jsonb | JSONB | Binary JSON |
| decimal(p,s) | DECIMAL(p,s) | Precision and scale |

## Migration Template

Generated migrations follow this structure:

```php
<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version{timestamp} extends AbstractMigration
{
    public function getDescription(): string
    {
        return '{operation} table {table_name}';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('{generated_sql}');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('{rollback_sql}');
    }
}
```

## Error Handling

The command will validate:
- ✅ Column type is supported
- ✅ Constraint syntax is valid
- ✅ Foreign key references exist
- ✅ SQL syntax is correct
- ✅ PHP syntax is valid
- ✅ Migration can be parsed by Doctrine

If validation fails, Claude will:
- Report the specific error
- Suggest corrections
- Not execute the migration

## Integration with Project

This command integrates with your existing workflow:
- Uses `make migrations-generate` (defined in Makefile)
- Creates migrations in `migrations/` directory
- Follows project coding standards (PSR-12, strict types)
- Compatible with `make migrations-migrate` for execution
- Works with Docker environment

## Advanced Usage

### Complex Table with All Features
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

### Batch Operations
```bash
# Generate multiple migrations in sequence
/doctrine-migration create users id:uuid:primary email:string:unique
/doctrine-migration create posts id:uuid:primary user_id:uuid --foreign-key user_id:users(id)
/doctrine-migration create comments id:uuid:primary post_id:uuid --foreign-key post_id:posts(id)
```

## Notes

- Always review generated SQL before executing migrations
- Use `--execute` flag only if you're confident in the migration
- Foreign keys require referenced tables to exist
- JSONB columns are ideal for flexible data (OAuth scopes, URIs, etc.)
- Timestamps should be managed by application or database triggers
- Use UUIDs for primary keys in distributed systems
