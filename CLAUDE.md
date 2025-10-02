# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

OAuth2 Server implementation using Symfony 7.3 and PHP 8.2+. Built with Doctrine DBAL (not ORM) for database management, JWT for authentication, and includes rate limiting capabilities.

**Tech Stack:**
- PHP 8.2+ with strict types (`declare(strict_types=1)`)
- Symfony 7.3.* (MicroKernelTrait)
- Doctrine DBAL 4.* (NO ORM - direct database access with Query Builder)
- Doctrine Migrations 3.8 for schema versioning
- PostgreSQL 17 database
- Redis 8.2 for caching/rate limiting
- JWT Framework (web-token/jwt-framework)
- Docker-based development environment

## Coding standards - NON-NEGOTIABLE

- Variable name MUST BE explicite. Avoid short names like x, tmp...
- Class, enum, interface names MUST BE explicite.
- Class, enum, interface names ALWAYS in pascal case.
- Interface names MUST BE ALWAYS SUFFIXED WITH "Interface"
- Enum names MUST BE ALWAYS SUFFIXED WITH "Enum"
- Trait names MUST BE ALWAYS SUFFIXED WITH "Trait"
- Abstract class names MUST BE ALWAYS PREFIXED WITH "Abstract"
- Variable and function names ALWAYS in camel case
- Code MUST follow PSR-12 coding standards and Symfony coding standards (see @docs/symfony_coding_standards.md)
- PSR-12 coding standards and Symfony coding standards can be fixed with PHP CS Fixer
- Code MUST follow the SOLID principles
- Design patterns MUST BE used as often as possible (ex: factory, strategy, builder, adapter, decorator, ...)
- PHPStan analyse MUST PASS
- always favors DTOs , Models, Value Objects over arrays when it is possible.
- Code as an PHP and Symfony Expert Senior Developper

## Development Commands

**Run all commands through Make or Docker Compose:**

```bash
# Installation
make install                  # Install dependencies via Composer

# Docker environment
make start                    # Start the app (nginx on :8000, postgres on :9876, redis on :9736)
make stop                     # Stop all containers

# Testing
make test                     # Run PHPUnit tests (uses compose.test.yaml)
bin/phpunit --filter TestName # Run specific test (inside container)

# Code quality
make static-code-analysis     # PHPStan at max level
make apply-cs                 # Apply PHP CS Fixer rules

# Database
make migrations-generate      # Generate empty migration file
make migrations-migrate       # Execute pending migrations

# CLI access
make php-cli                  # PHP shell in container
make composer-cli             # Composer shell in container
bin/console                   # Symfony console (inside container)
```

## Architecture & Structure

**Standard Symfony 7 structure with Docker orchestration:**

- `src/`: Application code (PSR-4 autoload: `App\`)
  - `Kernel.php`: MicroKernelTrait-based kernel
- `config/`: Symfony configuration (YAML-based)
  - `packages/`: Bundle configurations
  - `routes/`: Route definitions
- `tests/`: PHPUnit tests (PSR-4: `App\Tests\`)
- `migrations/`: Doctrine migration files
- `public/`: Web root
- `bin/`: Executable scripts (console, phpunit)
- `docker/`: Docker configuration files
- `var/`: Cache, logs (gitignored)
- `vendor/`: Composer dependencies (gitignored)

**Key Configuration Files:**
- `compose.yaml`: Main Docker setup (nginx, php, postgres, redis)
- `compose.test.yaml`: Test environment configuration
- `Dockerfile`: Multi-stage build (base-platform → dev)
- `.env`: Environment variables (development)
- `.env.test`: Test environment variables

## Code Quality Standards

**Strict quality enforcement:**

1. **PHP CS Fixer** (`.php-cs-fixer.dist.php`):
   - @Symfony, @PSR2 rules
   - Strict types required on all files
   - Short array/list syntax
   - Ordered imports and class elements
   - No unused imports

2. **PHPStan** (`phpstan.dist.neon`):
   - Level: max
   - Symfony & PHPUnit extensions enabled
   - Analyzes: bin/, config/, public/, src/, tests/
   - Requires Symfony container XML cache

3. **PHPUnit** (`phpunit.dist.xml`):
   - Fails on: deprecation, notice, warning
   - Bootstrap: tests/bootstrap.php
   - Coverage source: src/

## Docker Environment

**Service dependencies:**
- nginx (1.29-alpine) → php → database + redis
- All services use Alpine Linux 3.22
- Health checks configured for database and redis
- Persistent volumes for postgres and redis data

**Container workflow:**
- Development runs in `dev` Dockerfile target with Xdebug
- Tests use separate compose.test.yaml (isolated environment)
- All Make commands execute inside containers
- User ID mapping for file permissions (composer-cli)

## Development Guidelines

1. **Always use strict types**: All PHP files must start with `declare(strict_types=1);`
2. **Docker-first development**: Never run commands directly; use Make targets
3. **Database changes**: Generate migrations, never modify schema directly
4. **Code quality gates**: Run `make static-code-analysis` and `make apply-cs` before commits
5. **Test isolation**: Tests run in separate Docker environment with fresh database
6. **Symfony conventions**: Follow Symfony best practices for services, routing, configuration

## Database Layer Architecture

### DBAL-Only Pattern (No ORM)

This project uses **Doctrine DBAL exclusively** for database operations. We deliberately avoid Doctrine ORM to maintain full control over SQL queries and optimize performance.

**Key Principles:**
- All database access through `Doctrine\DBAL\Connection`
- Repository pattern with explicit SQL via Query Builder
- Models are plain PHP objects (readonly classes, no annotations)
- No entity manager, no lazy loading, no automatic persistence
- Manual hydration from database rows to Models

### Identity Management Strategy

**Application-Managed IDs**: Entity IDs are generated by the application layer before persisting to the database.

**Implementation Details:**
- **ID Generation**: UUIDs generated at application level (not database sequences)
- **Assignment Timing**: IDs assigned to models before `save()` or `create()` calls
- **Database Configuration**: Tables use `id UUID PRIMARY KEY` (no SERIAL/auto-increment)
- **Benefits**:
  - Predictable IDs before persistence (event sourcing, distributed systems)
  - Testability: deterministic IDs in tests
  - No database dependency for ID generation
  - Zero ID collision risk in distributed architectures

### Repository Pattern

**Structure:**
```
src/
├── Model/                    # Plain PHP readonly classes (no Doctrine annotations)
│   ├── OAuthClient.php
│   └── OAuthAuthorizationCode.php
└── Repository/
    ├── ClientRepositoryInterface.php
    ├── ClientRepository.php            # DBAL implementation
    ├── AuthorizationCodeRepositoryInterface.php
    └── AuthorizationCodeRepository.php # DBAL implementation
```

**Repository Guidelines:**
1. **Interface First**: Always define repository interface before implementation
2. **DBAL Query Builder**: Use `$connection->createQueryBuilder()` for complex queries
3. **Prepared Statements**: All queries use parameter binding (SQL injection prevention)
4. **Type Safety**: Explicit type declarations in insert/update operations
5. **JSON Handling**: PostgreSQL JSONB columns decoded/encoded manually
6. **Error Handling**: Catch `Doctrine\DBAL\Exception`, return null or throw `\RuntimeException`
7. **Hydration**: Private `hydrate*()` methods convert database rows to Models

**Example Repository Pattern:**
```php
final class ClientRepository implements ClientRepositoryInterface
{
    private const TABLE_NAME = 'oauth_clients';

    public function __construct(private readonly Connection $connection) {}

    public function find(string $id): ?OAuthClient
    {
        $qb = $this->connection->createQueryBuilder();
        $qb->select('*')->from(self::TABLE_NAME)->where('id = :id')->setParameter('id', $id);

        $result = $qb->executeQuery()->fetchAssociative();
        return $result ? $this->hydrateClient($result) : null;
    }

    private function hydrateClient(array $row): OAuthClient
    {
        return new OAuthClient(
            id: $row['id'],
            clientId: $row['client_id'],
            // ... manual mapping from row to constructor
        );
    }
}
```

### Testing Strategy

**Database Tests:**
- Use `Symfony\Bundle\FrameworkBundle\Test\KernelTestCase` for integration tests
- Test environment uses isolated PostgreSQL database via `compose.test.yaml`
- Migrations run automatically before tests via `tests/resources/tables.sql`
- Each test class manages its own transaction isolation (no shared state)
- DAMA Doctrine Test Bundle for automatic transaction rollback (optional)

**Test Pattern:**
```php
final class ClientRepositoryTest extends KernelTestCase
{
    private Connection $connection;
    private ClientRepository $repository;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = static::getContainer();
        $this->connection = $container->get('doctrine.dbal.default_connection');
        $this->repository = new ClientRepository($this->connection);
    }

    protected function tearDown(): void
    {
        self::ensureKernelShutdown();
    }

    public function testFind(): void
    {
        // Create test data
        // Execute repository method
        // Assert expectations
    }
}
```

### Migration Workflow

**All schema changes MUST use Doctrine Migrations:**

1. **Generate migration**: `make migrations-generate`
2. **Edit SQL**: Write raw SQL in `up()` and `down()` methods
3. **Verify**: `php bin/console doctrine:migrations:status`
4. **Execute**: `make migrations-migrate`
5. **Rollback if needed**: `php bin/console doctrine:migrations:migrate prev`

**Migration Best Practices:**
- Use `IF NOT EXISTS` for idempotent operations
- Always provide `down()` method for rollback
- Test migrations in test environment before production
- Use PostgreSQL-specific features (JSONB, UUID, partial indexes)
- Include descriptive `getDescription()` method

