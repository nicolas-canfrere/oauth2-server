# OAuth2 Server

OAuth2 Server implementation using Symfony 7.3 and PHP 8.2+.

## Tech Stack

- **PHP**: 8.2+ with strict types
- **Framework**: Symfony 7.3 (MicroKernelTrait)
- **Database**: PostgreSQL 17
- **Cache/Rate Limiting**: Redis 8.2
- **Database Layer**: Doctrine DBAL 4.* (NO ORM - direct SQL with Query Builder)
- **Migrations**: Doctrine Migrations 3.8
- **Authentication**: JWT Framework (web-token/jwt-framework)
- **Environment**: Docker-based development

## Requirements

- Docker & Docker Compose
- Make

## Installation

```bash
make install
```

## Development

### Start Environment

```bash
make start
```

Services:
- **nginx**: http://localhost:8000
- **PostgreSQL**: localhost:9876
- **Redis**: localhost:9736

### Stop Environment

```bash
make stop
```

### Database Migrations

```bash
# Generate migration
make migrations-generate

# Or use the custom migration generator
/doctrine-migration create <table_name> <column_definitions>

# Execute migrations
make migrations-migrate
```

### Testing

```bash
make test
```

Run specific test:
```bash
docker compose -f compose.test.yaml run --rm php bin/phpunit --filter TestName
```

### Code Quality

```bash
# Static analysis (PHPStan max level)
make static-code-analysis

# Apply coding standards (PHP CS Fixer)
make apply-cs
```

### CLI Access

```bash
# PHP shell
make php-cli

# Composer shell
make composer-cli

# Symfony console (inside container)
bin/console
```

## Project Structure

```
├── bin/                    # Executable scripts
├── config/                 # Symfony configuration
│   ├── packages/          # Bundle configurations
│   └── routes/            # Route definitions
├── docker/                # Docker configuration
├── docs/                  # Project documentation
├── migrations/            # Doctrine migrations
├── public/                # Web root
├── src/                   # Application code (PSR-4: App\)
│   ├── Model/            # Plain PHP readonly classes (no annotations)
│   ├── Repository/       # DBAL repositories with interfaces
│   ├── Service/          # Application services
│   └── Kernel.php        # Application kernel
├── tests/                 # PHPUnit tests (PSR-4: App\Tests\)
├── var/                   # Cache, logs (gitignored)
└── vendor/                # Dependencies (gitignored)
```

## Coding Standards

- **PSR-12** and **Symfony** coding standards (enforced by PHP CS Fixer)
- **Strict types** required on all PHP files: `declare(strict_types=1);`
- **SOLID principles** applied throughout
- **Design patterns** utilized (factory, strategy, builder, etc.)
- **PHPStan** max level analysis must pass
- **Naming conventions**:
  - Classes/Enums/Interfaces: PascalCase
  - Interfaces: Suffixed with `Interface`
  - Enums: Suffixed with `Enum`
  - Traits: Suffixed with `Trait`
  - Abstract classes: Prefixed with `Abstract`
  - Variables/functions: camelCase
  - Explicit names (avoid short names like `x`, `tmp`)

## Docker Environment

All commands run inside Docker containers. Never run PHP/Composer commands directly on host.

**Service Dependencies**:
- nginx → php → database + redis
- All services use Alpine Linux 3.22
- Health checks for database and redis
- Persistent volumes for data

## Architecture

### DBAL-Only Pattern (No ORM)

This project uses **Doctrine DBAL exclusively** for database operations:

- All database access through `Doctrine\DBAL\Connection`
- Repository pattern with explicit SQL via Query Builder
- Models are plain PHP readonly classes (no Doctrine annotations)
- No entity manager, no lazy loading, no automatic persistence
- Manual hydration from database rows to Models
- Application-managed UUIDs (generated before persistence)

### Security Features

**Token Hashing**: All OAuth2 tokens (authorization codes, access tokens, refresh tokens) are hashed using SHA-256 before storage for database breach protection.

**Token Blacklist**: Revoked tokens are stored in a blacklist to prevent reuse of compromised tokens.

### Implementation Status

**✅ Completed:**
- Client management (ClientRepository)
- Authorization code flow (AuthorizationCodeRepository)
- Refresh token management (RefreshTokenRepository)
- Token blacklist (TokenBlacklistRepository)
- Token hashing security (TokenHasher)
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

## Development Workflow

1. Start environment: `make start`
2. Make code changes
3. Run quality checks:
   - `make static-code-analysis`
   - `make apply-cs`
   - `make test`
4. Generate migrations if needed: `make migrations-generate`
5. Execute migrations: `make migrations-migrate`

## Documentation

- **CLAUDE.md**: Detailed guidance for AI-assisted development
- **docs/**: Additional documentation and specifications

## License

Proprietary