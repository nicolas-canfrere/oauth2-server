# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

OAuth2 Server implementation using Symfony 7.3 and PHP 8.2+. Built with Doctrine ORM for database management, JWT for authentication, and includes rate limiting capabilities.

**Tech Stack:**
- PHP 8.2+ with strict types (`declare(strict_types=1)`)
- Symfony 7.3.* (MicroKernelTrait)
- Doctrine ORM 3.5 with migrations
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

## Repositories

### Identity Management Strategy

**Application-Managed IDs**: This application uses an application-managed identity strategy where entity IDs are generated by the application layer before persisting to the database, rather than relying on database auto-increment or sequences.

#### Implementation Details

- **ID Generation**: Entity IDs are generated using application-level ID generators (UUID)
- **Assignment Timing**: IDs are assigned to entities before calling repository `save()` or `create()` methods
- **Database Configuration**: Database tables use `id` columns WITHOUT auto-increment/serial
- **Benefits**:
  - Predictable IDs before persistence (useful for event sourcing, distributed systems)
  - Testability: deterministic IDs in tests
  - No dependency on database-specific ID generation
  - Supports distributed architectures without ID collision risk methods

