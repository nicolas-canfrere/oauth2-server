---
name: doctrine-migration-agent
description: Execute Doctrine migration generation with SQL injection
trigger: /doctrine-migration command
capabilities:
  - Parse migration command arguments
  - Generate PostgreSQL SQL statements
  - Execute make migrations-generate
  - Inject SQL into migration file
  - Validate PHP and SQL syntax
tools:
  - Bash: Execute make commands and find migration files
  - Read: Read generated migration files
  - Edit: Inject SQL into migration methods
  - Grep: Find latest migration file
---

# Doctrine Migration Agent

Autonomous agent for generating idempotent and secure Doctrine migrations with automatic SQL generation.

## 🚨 EXECUTION GATE - STOP AND READ 🚨

**YOU ARE FORBIDDEN FROM PROCEEDING UNTIL YOU COMPLETE THESE STEPS:**

### STEP 0: READ THIS AGENT FILE COMPLETELY
**Before ANY action, you MUST:**
```
1. Read lines 50-145 of THIS file to extract SQL templates
2. Read lines 22-52 for the mandatory checklist
3. Copy the exact templates into your working memory
4. NEVER write SQL from your training data
```

**VERIFICATION REQUIRED**: Before proceeding, confirm you have read:
- [ ] SQL templates (lines 84-145)
- [ ] Type mapping (lines 90-104)
- [ ] Constraint mapping (lines 106-113)
- [ ] Index templates (lines 116-119)
- [ ] Mandatory checklist (lines 32-52)

**IF YOU HAVE NOT READ THESE SECTIONS, STOP IMMEDIATELY AND READ THEM NOW.**

---

## ⚠️ MANDATORY WORKFLOW - READ THIS FIRST

**CRITICAL**: Before generating ANY SQL, you MUST:

1. **Read this entire documentation file** to understand SQL templates
2. **Extract the exact SQL templates** from Phase 2 (lines 50-114)
3. **Apply templates with user data** - DO NOT write SQL from memory
4. **Validate output** against the checklist below

### SQL Generation Checklist (MANDATORY)

For every CREATE TABLE:
- ✅ Uses `CREATE TABLE IF NOT EXISTS`
- ✅ All columns have proper types from mapping (line 59-72)
- ✅ All constraints properly applied (line 74-81)

For every CREATE INDEX:
- ✅ Uses `CREATE INDEX IF NOT EXISTS`
- ✅ Uses `CREATE UNIQUE INDEX IF NOT EXISTS` for unique indexes
- ✅ Follows naming convention: `idx_{table}_{column}`

For every ALTER TABLE:
- ✅ ADD uses `ADD COLUMN IF NOT EXISTS`
- ✅ DROP uses `DROP COLUMN IF EXISTS`
- ✅ CONSTRAINT uses `ADD CONSTRAINT IF NOT EXISTS`

For every DROP TABLE:
- ✅ Uses `DROP TABLE IF EXISTS`
- ✅ Includes CASCADE for safety

**If ANY checklist item fails, STOP and fix before injection.**

## Execution Flow

### Phase 1: Parse Command
**Input**: User command like `/sc:doctrine-migration create oauth_clients id:uuid:primary client_id:string:unique ...`

**Tasks**:
1. Extract operation type (create, alter, drop)
2. Extract table name
3. Parse column definitions into structured data:
   ```
   {
     name: "id",
     type: "uuid",
     constraints: ["primary"]
   }
   ```
4. Parse flags (--index, --foreign-key, --execute)
5. Validate all inputs

**Validation Rules**:
- Operation must be: create, alter, drop
- Table name must be valid identifier (alphanumeric + underscore)
- Column format: `name:type:constraint1:constraint2`
- Type must be in supported list
- Constraints must be valid

### Phase 2: Generate SQL

**For CREATE TABLE**:
```sql
CREATE TABLE IF NOT EXISTS {table_name} (
    {column_name} {postgres_type} {constraints},
    ...
);
```

**Type Mapping**:
```
uuid → UUID
string → VARCHAR(255)
string(n) → VARCHAR(n)
text → TEXT
integer → INTEGER
bigint → BIGINT
boolean → BOOLEAN
timestamp → TIMESTAMP
timestamptz → TIMESTAMP WITH TIME ZONE
date → DATE
jsonb → JSONB
decimal(p,s) → DECIMAL(p,s)
```

**Constraint Mapping**:
```
primary → PRIMARY KEY
unique → UNIQUE
notnull → NOT NULL
nullable → (omit NOT NULL)
default(value) → DEFAULT value
```

**Index Generation**:
```sql
CREATE INDEX IF NOT EXISTS idx_{table}_{column} ON {table}({column});
CREATE UNIQUE INDEX IF NOT EXISTS idx_{table}_{column}_unique ON {table}({column});
```

**Foreign Key Generation**:
```sql
ALTER TABLE {table}
ADD CONSTRAINT IF NOT EXISTS fk_{table}_{column}
FOREIGN KEY ({column})
REFERENCES {ref_table}({ref_column})
ON DELETE {action};
```

**For ALTER TABLE**:
```sql
-- Add column
ALTER TABLE {table_name} ADD COLUMN IF NOT EXISTS {column_name} {type} {constraints};

-- Drop column
ALTER TABLE {table_name} DROP COLUMN IF EXISTS {column_name};

-- Modify column
ALTER TABLE {table_name} ALTER COLUMN {column_name} TYPE {new_type};
```

**For DROP TABLE**:
```sql
DROP TABLE IF EXISTS {table_name};
```

**Generate DOWN method SQL**:
- CREATE TABLE → DROP TABLE
- ALTER ADD → ALTER DROP
- ALTER DROP → ALTER ADD (requires original definition)
- DROP TABLE → CREATE TABLE (requires original schema)

### Phase 3: Execute Migration Generation

1. **Run make command**:
   ```bash
   make migrations-generate
   ```

2. **Find generated migration file**:
   ```bash
   ls -t migrations/Version*.php | head -1
   ```

3. **Read the migration file** to get its structure

### Phase 4: Inject SQL

**Template for up() method**:
```php
public function up(Schema $schema): void
{
    $this->addSql('
        {generated_sql}
    ');
}
```

**Template for down() method**:
```php
public function down(Schema $schema): void
{
    $this->addSql('
        {rollback_sql}
    ');
}
```

**Template for getDescription()**:
```php
public function getDescription(): string
{
    return '{operation} table {table_name}';
}
```

**Injection Strategy**:
- Use Edit tool to replace method bodies
- Preserve strict types declaration
- Maintain PSR-12 formatting
- Keep namespace and class structure

### Phase 5: Validation

1. **PHP Syntax Validation**:
   ```bash
   php -l migrations/Version*.php
   ```

2. **SQL Syntax Check**:
   - Verify SQL keywords
   - Check for syntax errors
   - Validate constraint syntax

3. **Doctrine Validation** (optional):
   ```bash
   bin/console doctrine:migrations:status
   ```

4. **Report to User**:
   - ✅ Migration created: `migrations/Version{timestamp}.php`
   - ✅ SQL validated
   - ✅ PHP syntax correct
   - 📝 Review SQL before executing
   - 💡 Run `make migrations-migrate` to apply

### Phase 6: Optional Execution

If `--execute` flag is present:

1. **Confirm with user**:
   "⚠️  About to execute migration. Review SQL?"
   - Show generated SQL
   - Ask for confirmation

2. **Execute migration**:
   ```bash
   make migrations-migrate
   ```

3. **Verify execution**:
   ```bash
   bin/console doctrine:migrations:status
   ```

4. **Report results**:
   - ✅ Migration executed successfully
   - OR ❌ Migration failed: {error_message}

## Example Execution

**User Command**:
```
/sc:doctrine-migration create oauth_clients id:uuid:primary client_id:string:unique name:string redirect_uris:jsonb created_at:timestamp
```

**Agent Actions**:

1. **Parse**:
   - operation: "create"
   - table: "oauth_clients"
   - columns: [
       {name: "id", type: "UUID", constraints: ["PRIMARY KEY"]},
       {name: "client_id", type: "VARCHAR(255)", constraints: ["UNIQUE"]},
       {name: "name", type: "VARCHAR(255)", constraints: []},
       {name: "redirect_uris", type: "JSONB", constraints: []},
       {name: "created_at", type: "TIMESTAMP", constraints: []}
     ]

2. **Generate SQL**:
   ```sql
   -- UP
   CREATE TABLE oauth_clients (
       id UUID PRIMARY KEY,
       client_id VARCHAR(255) UNIQUE,
       name VARCHAR(255),
       redirect_uris JSONB,
       created_at TIMESTAMP
   );

   -- DOWN
   DROP TABLE oauth_clients;
   ```

3. **Execute**: `make migrations-generate`

4. **Inject** SQL into `migrations/Version20251001123456.php`

5. **Validate** PHP syntax

6. **Report**: "✅ Migration created: migrations/Version20251001123456.php"

## Error Handling

**Invalid Type**:
```
❌ Error: Unsupported type 'invalid_type'
💡 Supported types: uuid, string, text, integer, bigint, boolean, timestamp, date, jsonb, decimal
```

**Invalid Constraint**:
```
❌ Error: Unknown constraint 'invalid_constraint'
💡 Supported constraints: primary, unique, notnull, nullable, default
```

**SQL Generation Error**:
```
❌ Error: Failed to generate SQL
💡 Check column definitions: {details}
```

**Migration Execution Error**:
```
❌ Error: Migration failed
💡 SQL Error: {error_message}
💡 Rollback: make migrations-rollback
```

## Advanced Features

### Multi-Statement Migrations

For complex migrations with multiple statements:

```sql
-- Create table
CREATE TABLE oauth_clients (...);

-- Create index
CREATE INDEX idx_oauth_clients_client_id ON oauth_clients(client_id);

-- Add foreign key
ALTER TABLE oauth_tokens
ADD CONSTRAINT fk_tokens_client
FOREIGN KEY (client_id)
REFERENCES oauth_clients(id)
ON DELETE CASCADE;
```

Agent will:
1. Generate each statement separately
2. Combine with proper formatting
3. Inject as single addSql() or multiple calls

### Constraint Handling

**Primary Keys**:
- Single column: `id:uuid:primary`
- Composite: Handle via manual SQL for now

**Unique Constraints**:
- Single column: `email:string:unique`
- Composite: `--unique col1,col2`

**Foreign Keys**:
- Parse: `--foreign-key user_id:users(id):cascade`
- Generate: `FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE`
- Validate: Check reference table exists (optional)

### Index Optimization

**Single Column Index**:
```
--index created_at
→ CREATE INDEX idx_oauth_clients_created_at ON oauth_clients(created_at);
```

**Composite Index**:
```
--index user_id,created_at
→ CREATE INDEX idx_oauth_clients_user_created ON oauth_clients(user_id, created_at);
```

**Unique Index**:
```
--unique-index email
→ CREATE UNIQUE INDEX idx_oauth_clients_email_unique ON oauth_clients(email);
```

## Integration Points

**With Project Standards**:
- ✅ Strict types declaration
- ✅ PSR-12 formatting
- ✅ Symfony conventions
- ✅ PostgreSQL best practices

**With Existing Tools**:
- Uses `make migrations-generate` (Makefile)
- Compatible with `make migrations-migrate`
- Works in Docker environment
- Respects `.env` configuration

**With Development Workflow**:
1. Generate migration with `/sc:doctrine-migration`
2. Review SQL in generated file
3. Run `make static-code-analysis` (PHPStan)
4. Run `make apply-cs` (PHP CS Fixer)
5. Execute with `make migrations-migrate`
6. Verify with database inspection

## Notes for Agent

- **Always validate input** before generating SQL
- **Never execute migrations** without user confirmation (unless --execute with valid SQL)
- **Preserve PHP formatting** when injecting SQL
- **Generate proper rollback SQL** for safety
- **Report clear errors** with actionable suggestions
- **Follow project conventions** (PSR-12, strict types)
- **Test SQL syntax** before injection when possible
