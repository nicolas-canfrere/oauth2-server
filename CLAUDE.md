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
- DTO class names MUST BE ALWAYS SUFFIXED WITH "DTO"
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

**Hexagonal Architecture (Ports & Adapters)**

This project follows hexagonal architecture principles with clear separation of concerns:

```
src/
├── Domain/                      # Business logic (core)
│   ├── OAuthClient/            # Client aggregate
│   │   ├── Model/              # Domain models (readonly classes)
│   │   ├── Repository/         # Repository interfaces (ports)
│   │   ├── Service/            # Domain services
│   │   ├── Security/           # Client authentication logic
│   │   └── Exception/          # Domain-specific exceptions
│   ├── User/                   # User aggregate
│   ├── Audit/                  # Audit logging aggregate
│   ├── AuthorizationCode/      # Authorization code aggregate
│   ├── RefreshToken/           # Refresh token aggregate
│   ├── TokenBlacklist/         # Token blacklist aggregate
│   ├── Scope/                  # OAuth scopes aggregate
│   ├── Key/                    # Cryptographic keys aggregate
│   ├── Consent/                # User consent aggregate
│   ├── Security/               # Security services (TokenHasher, OpaqueTokenGenerator)
│   └── Shared/                 # Shared domain components
│       └── Exception/          # Shared exceptions (RepositoryException)
│
├── Application/                 # Use cases & orchestration
│   └── AccessToken/
│       ├── UseCase/            # Application use cases (orchestrate domain logic)
│       ├── GrantHandler/       # OAuth2 grant type handlers
│       ├── Service/            # Application services (JwtTokenGenerator)
│       ├── DTO/                # Data Transfer Objects
│       ├── Enum/               # Application enums (GrantType)
│       └── Exception/          # Application-level exceptions
│
└── Infrastructure/              # External adapters
    ├── Persistance/Doctrine/   # Database adapters (DBAL implementations)
    │   └── Repository/         # Concrete repository implementations
    ├── Http/Controller/        # HTTP endpoints (Symfony controllers)
    ├── Cli/Command/            # Console commands
    ├── Audit/                  # Audit infrastructure (AuditLogger, EventSubscribers)
    ├── RateLimiter/            # Rate limiting service (Redis)
    ├── Security/               # Symfony Security integration
    └── AccessToken/Service/    # JWT token generation infrastructure
```

**Dependency Flow (Hexagonal Architecture):**
- **Domain** ← Application ← Infrastructure
- Domain has NO dependencies on Application or Infrastructure
- Application depends ONLY on Domain interfaces (ports)
- Infrastructure implements Domain interfaces (adapters)

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
7. **Hexagonal architecture rules**:
   - Domain layer NEVER imports from Application or Infrastructure
   - Application layer imports ONLY from Domain
   - Infrastructure layer implements Domain interfaces
   - New features: start with Domain (models, interfaces), then Application (use cases), then Infrastructure (adapters)

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

### Repository Pattern (Hexagonal Implementation)

**Repository interfaces are ports (Domain layer), implementations are adapters (Infrastructure layer):**

```
Domain Layer (Ports):
src/Domain/{Aggregate}/Repository/{Aggregate}RepositoryInterface.php

Infrastructure Layer (Adapters):
src/Infrastructure/Persistance/Doctrine/Repository/{Aggregate}Repository.php
```

**Example:**
- `src/Domain/OAuthClient/Repository/ClientRepositoryInterface.php` (port)
- `src/Infrastructure/Persistance/Doctrine/Repository/ClientRepository.php` (adapter)

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

## Security Features

### Token Hashing

**All OAuth2 tokens (authorization codes, access tokens, refresh tokens) MUST be hashed before storage.**

**Implementation:**
- **Service**: `TokenHasher` implements `TokenHasherInterface`
- **Algorithm**: SHA-256 hashing for secure token storage
- **Usage**: Hash tokens before `save()`, compare hashed values on `find()`
- **Tables**: All token tables store hashed values, never plain text
- **Benefits**:
  - Database breach protection: tokens cannot be extracted from database
  - Compliance: Follows OAuth2 security best practices
  - Performance: Fast hash comparison for token validation

**Pattern:**
```php
// Hashing tokens before storage
$hashedToken = $tokenHasher->hash($plainTextToken);
$repository->save($entity->withHashedToken($hashedToken));

// Validating tokens
$hashedToken = $tokenHasher->hash($providedToken);
$entity = $repository->findByHashedToken($hashedToken);
```

### Token Blacklist

**Revoked tokens are stored in blacklist for security.**

**Implementation:**
- **Repository**: `TokenBlacklistRepository` with `TokenBlacklistRepositoryInterface`
- **Model**: `OAuthTokenBlacklist` stores hashed tokens with revocation metadata
- **Purpose**: Prevent reuse of revoked access/refresh tokens
- **Strategy**: Check blacklist before token validation
- **Cleanup**: Periodic removal of expired blacklisted tokens

### Audit Logging

**All security-relevant OAuth2 events MUST be logged for compliance and security analysis.**

**Implementation:**
- **Service**: `AuditLogger` implements `AuditLoggerInterface` with dedicated Monolog channel
- **Repository**: `AuditLogRepository` with `AuditLogRepositoryInterface` for DBAL operations
- **Model**: `OAuthAuditLog` immutable value object for audit records
- **DTO**: `AuditEventDTO` with factory methods for type-safe event creation
- **Enum**: `AuditEventTypeEnum` defines all trackable OAuth2 security events
- **Storage**: PostgreSQL `oauth_audit_logs` table with optimized indexes
- **Retention**: Configurable via `AUDIT_LOG_RETENTION_DAYS` environment variable (default: 90 days)
- **Cleanup**: `AuditLogCleanupCommand` for automated log rotation (run via CRON)

**Logged Events:**
- Authentication: login success/failure
- Token issuance: access tokens, refresh tokens, authorization codes
- Token revocation: access/refresh token revocation
- Client management: client created/updated/deleted
- Security events: rate limits, invalid credentials, suspicious activity

**Usage Pattern:**
```php
// Log successful login
$auditLogger->logEvent(
    AuditEventDTO::loginSuccess($userId, $ipAddress, $userAgent)
);

// Log token issuance
$auditLogger->logEvent(
    AuditEventDTO::accessTokenIssued($userId, $clientId, $jti, $scopes, $ipAddress)
);

// Log security event
$auditLogger->logEvent(
    AuditEventDTO::rateLimitExceeded($limiterName, $ipAddress, $userId)
);
```

**Log Format:**
- JSON-formatted logs to `var/log/audit.log` (dev/test) or `php://stderr` (production)
- Structured fields: event_type, level, message, context, user_id, client_id, ip_address, user_agent, timestamp
- Monolog integration with dedicated `audit` channel for separation from application logs

**Querying Audit Logs:**
```php
// Find by user
$logs = $auditLogRepository->findByUserId($userId, limit: 100);

// Find by client
$logs = $auditLogRepository->findByClientId($clientId, limit: 100);

// Find by event type
$logs = $auditLogRepository->findByEventType(AuditEventTypeEnum::LOGIN_FAILURE);

// Find by date range
$logs = $auditLogRepository->findByDateRange($startDate, $endDate);
```

**Maintenance:**
```bash
# Run cleanup manually
bin/console audit:cleanup

# Dry-run to preview deletion
bin/console audit:cleanup --dry-run

# Custom retention period
bin/console audit:cleanup --days=30

# Recommended CRON schedule (daily at 3 AM)
0 3 * * * php bin/console audit:cleanup
```

## Current Implementation Status

**✅ Completed:**
- Client management (ClientRepository)
- Authorization code flow (AuthorizationCodeRepository)
- Refresh token management (RefreshTokenRepository)
- Token blacklist (TokenBlacklistRepository)
- Token hashing security (TokenHasher)
- Audit logging system (AuditLogger, AuditLogRepository)
- Audit log cleanup automation (AuditLogCleanupCommand)
- Docker development environment
- Test infrastructure with DAMA bundle

**🔄 In Progress:**
- Access token management
- JWT token generation and validation
- OAuth2 endpoint controllers

**📋 Planned:**
- Rate limiting with Redis
- Scope management
- Client credentials flow
- PKCE support

