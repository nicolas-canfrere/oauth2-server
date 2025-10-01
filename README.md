# OAuth2 Server

OAuth2 Server implementation using Symfony 7.3 and PHP 8.2+.

## Tech Stack

- **PHP**: 8.2+ with strict types
- **Framework**: Symfony 7.3
- **Database**: PostgreSQL 17
- **Cache/Rate Limiting**: Redis 8.2
- **ORM**: Doctrine ORM 3.5
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
├── migrations/            # Doctrine migrations
├── public/                # Web root
├── src/                   # Application code (PSR-4: App\)
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

## Development Workflow

1. Start environment: `make start`
2. Make code changes
3. Run quality checks:
   - `make static-code-analysis`
   - `make apply-cs`
   - `make test`
4. Generate migrations if needed: `make migrations-generate`
5. Execute migrations: `make migrations-migrate`

## License

Proprietary