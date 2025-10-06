# CLAUDE.md

OAuth2 Server implementation using Symfony 7.3 and PHP 8.2+. Hexagonal architecture with Doctrine DBAL (no ORM).

**Tech Stack:** PHP 8.2+ (strict types), Symfony 7.3, Doctrine DBAL 4.*, PostgreSQL 17, Redis 8.2, JWT, Docker

## Coding Standards

**Naming:**
- Classes/Enums/Interfaces: PascalCase + suffixes (Interface, Enum, Trait) or prefix (Abstract)
- DTOs: PascalCase + "DTO" suffix
- Variables/Functions: camelCase, explicit names (no x, tmp)

**Quality:**
- PSR-12, Symfony coding standards (fix with `make apply-cs`)
- SOLID principles + design patterns
- PHPStan must pass (`make static-code-analysis`)
- Favor DTOs/Models/Value Objects over arrays
- All files start with `declare(strict_types=1);`

## Commands

```bash
make install                  # Install dependencies
make start / stop             # Docker environment (nginx :8000, postgres :9876, redis :9736)
make test                     # Run PHPUnit tests
make test R="--filter Name"   # Run specific test
make static-code-analysis     # PHPStan
make apply-cs                 # PHP CS Fixer
make migrations-generate      # Generate migration
make migrations-migrate       # Run migrations
make php-cli / composer-cli   # Container shells
```

## Architecture

**Hexagonal (Ports & Adapters):**
```
Domain/                  # Business logic (core) - NO external dependencies
├── {Aggregate}/Model/          # Readonly classes
├── {Aggregate}/Repository/     # Interfaces (ports)
└── {Aggregate}/Service/        # Domain services

Application/             # Use cases - depends ONLY on Domain
├── UseCase/                    # Orchestration
├── DTO/                        # Data transfer
└── Service/                    # Application services

Infrastructure/          # External adapters - implements Domain interfaces
├── Persistance/Doctrine/       # Repository implementations
├── Http/Controller/            # HTTP endpoints
└── Cli/Command/                # Console commands
```

**Dependency Rules:**
- Domain → NOTHING (pure business logic)
- Application → Domain interfaces only
- Infrastructure → implements Domain interfaces
- Feature development: Domain (models/interfaces) → Application (use cases) → Infrastructure (adapters)

## Database

**DBAL Only (No ORM):**
- Direct `Doctrine\DBAL\Connection` + Query Builder
- Models = plain PHP readonly classes (no annotations)
- Manual hydration from rows to Models
- Application-managed UUIDs (generated before persistence)

**Repository Pattern:**
- Interface: `src/Domain/{Aggregate}/Repository/{Aggregate}RepositoryInterface.php`
- Implementation: `src/Infrastructure/Persistance/Doctrine/Repository/{Aggregate}Repository.php`
- Use Query Builder for complex queries, parameter binding for SQL injection prevention

**Migrations:**
1. `make migrations-generate` → Edit SQL in `up()`/`down()` → `make migrations-migrate`
2. Always provide rollback in `down()`, use `IF NOT EXISTS`, test before production

## Security

**Token Hashing:**
- ALL OAuth2 tokens MUST be SHA-256 hashed before storage (never plain text)
- Use `TokenHasher` service before `save()`, compare hashed values on `find()`

**Audit Logging:**
- ALL OAuth2 security events MUST be logged via `AuditLogger`
- Events: authentication, token issuance/revocation, client management, security violations

**Token Blacklist:**
- Store revoked tokens to prevent reuse
- Check blacklist before token validation

