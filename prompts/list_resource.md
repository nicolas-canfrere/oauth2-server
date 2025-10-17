# Prompt: Implement List/Pagination for a Resource

Use this prompt to implement a complete list/pagination system for any aggregate in the OAuth2 Server project.

## Prompt Template

```
Create a pagination system for listing [RESOURCE_NAME] resources.

Requirements:
- Create Query and QueryHandler in src/Application/[Aggregate]/List[Resource]/
- Add paginate() method to [Resource]RepositoryInterface
- Implement paginate() in [Resource]Repository using Doctrine DBAL
- Pagination parameters: page (default: 1), itemsPerPage (default: 10, max: 100), orderBy (default: 'asc'), sortField (default: '[FIELD_NAME]')
- Sortable fields with whitelist: [FIELD_1, FIELD_2, FIELD_3] (prevents SQL injection)
- Return structure: {[resources]: Resource[], total: int, page: int, itemsPerPage: int, totalPages: int}
- Application-layer validation in Query constructor
- Follow hexagonal architecture principles
- Ensure PHPStan and PHP CS Fixer compliance
```

## Example Usage

```
Create a pagination system for listing User resources.

Requirements:
- Create Query and QueryHandler in src/Application/User/ListUser/
- Add paginate() method to UserRepositoryInterface
- Implement paginate() in UserRepository using Doctrine DBAL
- Pagination parameters: page (default: 1), itemsPerPage (default: 10, max: 100), orderBy (default: 'asc'), sortField (default: 'email')
- Sortable fields with whitelist: email, created_at, id (prevents SQL injection)
- Return structure: {users: User[], total: int, page: int, itemsPerPage: int, totalPages: int}
- Application-layer validation in Query constructor
- Follow hexagonal architecture principles
- Ensure PHPStan and PHP CS Fixer compliance
```

## Implementation Checklist

When you receive this prompt, follow these steps:

### 1. Domain Layer - Repository Interface
- [ ] Add `paginate()` method to `src/Domain/[Aggregate]/Repository/[Resource]RepositoryInterface.php`
- [ ] Method signature: `public function paginate(int $page, int $itemsPerPage, string $orderBy = 'asc', string $sortField = '[default_field]'): array;`
- [ ] Add PHPDoc with parameter descriptions and return type annotation
- [ ] Document allowed sortable fields in PHPDoc
- [ ] Include `@throws \RuntimeException` annotation

### 2. Infrastructure Layer - Repository Implementation
- [ ] Implement `paginate()` in `src/Infrastructure/Persistance/Doctrine/Repository/[Resource]Repository.php`
- [ ] **CRITICAL**: Place ALL validation BEFORE try-catch block to prevent division by zero
- [ ] Validate page >= 1 (throw `\InvalidArgumentException`)
- [ ] Validate itemsPerPage >= 1 (throw `\InvalidArgumentException`)
- [ ] Validate and normalize order direction (must be 'asc' or 'desc', throw `\InvalidArgumentException`)
- [ ] Validate sortField against whitelist array (throw `\InvalidArgumentException` with allowed fields listed)
- [ ] Define `$allowedFields` whitelist for sortable columns
- [ ] Execute COUNT(*) query to get total records
- [ ] **Cast count result**: `$total = (int) $countResult['count'];`
- [ ] Calculate offset: `($page - 1) * $itemsPerPage`
- [ ] Calculate totalPages: `(int) ceil($total / $itemsPerPage)`
- [ ] Build query with:
  - `->orderBy($sortField, $orderBy)` (use variable, not hardcoded field)
  - `->setMaxResults($itemsPerPage)`
  - `->setFirstResult($offset)`
- [ ] Add performance documentation comment before COUNT(*) explaining optimization options
- [ ] Hydrate results to domain models using existing hydration method
- [ ] Return array with structure: `['[resources]' => [], 'total' => int, 'page' => int, 'itemsPerPage' => int, 'totalPages' => int]`
- [ ] Wrap database operations in try-catch and throw RepositoryException on failure

### 3. Application Layer - Query
- [ ] Create `src/Application/[Aggregate]/List[Resource]/List[Resource]Query.php`
- [ ] Make class `final readonly`
- [ ] Add comprehensive PHPDoc documenting all parameters with constraints
- [ ] Add `@throws \InvalidArgumentException` annotation
- [ ] Add constructor with promoted properties:
  - `public int $page = 1`
  - `public int $itemsPerPage = 10`
  - `public string $orderBy = 'asc'`
  - `public string $sortField = '[default_field]'`
- [ ] **CRITICAL**: Validate ALL parameters in constructor (Application-layer validation)
- [ ] Validate `$page >= 1`
- [ ] Validate `$itemsPerPage` between 1 and 100 (upper bound prevents resource exhaustion)
- [ ] Normalize and validate `$orderBy` (strtolower, check against ['asc', 'desc'])
- [ ] Validate `$sortField` against same whitelist as repository
- [ ] Throw `\InvalidArgumentException` with clear messages for all validation failures

### 4. Application Layer - QueryHandler
- [ ] Create `src/Application/[Aggregate]/List[Resource]/List[Resource]QueryHandler.php`
- [ ] Make class `final readonly`
- [ ] Inject `[Resource]RepositoryInterface` in constructor
- [ ] Implement `__invoke(List[Resource]Query $query): array`
- [ ] Call `$this->repository->paginate()` with ALL query parameters including sortField
- [ ] Use named arguments for clarity: `page: $query->page, itemsPerPage: $query->itemsPerPage, orderBy: $query->orderBy, sortField: $query->sortField`
- [ ] Add PHPDoc return type annotation with full structure
- [ ] Return repository result directly

### 5. Quality Checks
- [ ] Run `make static-code-analysis` (PHPStan must pass)
- [ ] Run `make apply-cs` (apply PHP CS Fixer rules)
- [ ] Verify all files start with `declare(strict_types=1);`
- [ ] Verify proper namespace structure
- [ ] Verify readonly classes where appropriate

## Architecture Notes

**Dependency Flow:**
- Domain (Repository Interface) <- Application (Query/Handler) -> Infrastructure (Repository Implementation)
- Handler depends on Domain interface, not Infrastructure implementation
- Infrastructure implements Domain contracts

**DBAL Pattern:**
- Use Query Builder for all queries
- Use parameterized queries (avoid SQL injection)
- Manual hydration from arrays to domain models
- No ORM annotations

**Validation Strategy:**
- **Application Layer (Query)**: Validates business rules and input constraints with `\InvalidArgumentException`
  - Page >= 1
  - ItemsPerPage between 1 and 100 (upper bound prevents resource exhaustion)
  - OrderBy in ['asc', 'desc']
  - SortField in whitelist
- **Infrastructure Layer (Repository)**: Secondary validation as safety net, same rules
- Throw `\InvalidArgumentException` for invalid input, `RepositoryException` for persistence failures
- Domain models remain readonly and pure
- **Double validation** (Application + Infrastructure) provides defense in depth

## Expected File Structure

```
src/
  Domain/
    [Aggregate]/
      Repository/
        [Resource]RepositoryInterface.php (+ paginate method)
  Application/
    [Aggregate]/
      List[Resource]/
        List[Resource]Query.php (new)
        List[Resource]QueryHandler.php (new)
  Infrastructure/
    Persistance/
      Doctrine/
        Repository/
          [Resource]Repository.php (+ paginate implementation)
```

## Return Structure Example

```php
[
    'users' => [User, User, User], // Array of domain models
    'total' => 42,                 // Total count in database
    'page' => 2,                   // Current page
    'itemsPerPage' => 10,          // Items per page
    'totalPages' => 5              // ceil(42 / 10) = 5
]
```

## Security Considerations

**SQL Injection Prevention:**
- ALWAYS use whitelist validation for `sortField` parameter
- Define `$allowedFields` array in both Query and Repository
- NEVER concatenate user input directly into ORDER BY clause
- Query Builder parameterization protects values but not column names

**Resource Exhaustion:**
- Maximum `itemsPerPage` of 100 prevents memory/performance attacks
- Consider rate limiting on pagination endpoints
- Monitor COUNT(*) query performance on large tables

**Data Exposure:**
- Consider role-based filtering if users should only see subset of data
- Log unusual pagination requests (very high page numbers, max itemsPerPage)

## Performance Notes

**COUNT(*) Optimization:**
Document in repository that COUNT(*) performs full table scan on PostgreSQL:
- Acceptable for < 100k rows
- For larger datasets consider:
  1. PostgreSQL's `pg_class.reltuples` for approximate count
  2. Caching total count with TTL
  3. Cursor-based pagination (keyset pagination)
  4. Showing only "Next/Previous" without total

**Index Requirements:**
- Ensure database indexes exist on sortable fields for optimal performance
- Monitor query execution time and add indexes as needed

## Common Variations

**Custom Default Field:**
Replace `email` with appropriate default field for your resource (e.g., `name`, `created_at`, `code`)

**Additional Sortable Fields:**
Add fields to `$allowedFields` whitelist in both Query and Repository

**Filters:**
Add filter parameters to Query constructor and WHERE clauses in repository implementation

**Role-Based Access:**
Add role-based filtering in repository if needed (e.g., only show resources from same tenant)
