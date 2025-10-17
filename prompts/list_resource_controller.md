# Procédure de création d'un contrôleur API de listing de ressource

Cette procédure décrit comment créer un contrôleur API RESTful pour lister une ressource avec pagination et tri, en suivant l'architecture hexagonale et les standards du projet.

## Prérequis

Avant de créer le contrôleur, assurez-vous que les éléments suivants existent déjà :

- ✅ Model de domaine dans `src/Domain/{Resource}/Model/{Resource}.php`
- ✅ Query handler dans `src/Application/{Resource}/List{Resource}/List{Resource}QueryHandler.php`
- ✅ Query class dans `src/Application/{Resource}/List{Resource}/List{Resource}Query.php`
- ✅ Repository interface avec méthode `paginate()` dans `src/Domain/{Resource}/Repository/{Resource}RepositoryInterface.php`

## Structure des fichiers à créer

```
src/Infrastructure/Http/Controller/{Resource}/
├── List{Resources}Controller.php      # Contrôleur principal
└── List{Resources}QueryDTO.php        # DTO pour validation des query params
```

## Étape 1 : Créer le DTO de validation (Query DTO)

**Fichier** : `src/Infrastructure/Http/Controller/{Resource}/List{Resources}QueryDTO.php`

### Template

```php
<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Controller\{Resource};

use Symfony\Component\Validator\Constraints as Assert;

/**
 * Data Transfer Object for {resource} listing query parameters.
 */
final readonly class List{Resources}QueryDTO
{
    /**
     * @param int    $page         Current page number (1-indexed, minimum: 1)
     * @param int    $itemsPerPage Number of items per page (minimum: 1, maximum: 100)
     * @param string $orderBy      Sort direction: 'asc' or 'desc'
     * @param string $sortField    Field to sort by: {list allowed fields}
     */
    public function __construct(
        #[Assert\Positive(message: 'Page must be greater than 0.')]
        public int $page = 1,
        #[Assert\Positive(message: 'Items per page must be greater than 0.')]
        #[Assert\LessThanOrEqual(value: 100, message: 'Items per page cannot exceed 100.')]
        public int $itemsPerPage = 10,
        #[Assert\Choice(choices: ['asc', 'desc'], message: 'Order direction must be "asc" or "desc".')]
        public string $orderBy = 'asc',
        #[Assert\Choice(choices: [{allowed_fields}], message: 'Invalid sort field. Allowed fields: {allowed_fields}.')]
        public string $sortField = '{default_sort_field}',
    ) {
    }
}
```

### Instructions de personnalisation

1. **Remplacer `{Resource}`** par le nom de la ressource (PascalCase, singulier) : `User`, `OAuthClient`, `Token`
2. **Remplacer `{Resources}`** par le pluriel : `Users`, `OAuthClients`, `Tokens`
3. **Remplacer `{resource}`** par le nom en minuscule : `user`, `oauth client`, `token`
4. **Remplacer `{allowed_fields}`** par les champs autorisés pour le tri (ex: `'email', 'created_at', 'id'`)
5. **Remplacer `{default_sort_field}`** par le champ de tri par défaut (ex: `'email'`, `'name'`, `'created_at'`)

### Contraintes de validation disponibles

- `#[Assert\Positive]` : valeur > 0
- `#[Assert\PositiveOrZero]` : valeur >= 0
- `#[Assert\LessThanOrEqual(value: N)]` : valeur <= N
- `#[Assert\GreaterThanOrEqual(value: N)]` : valeur >= N
- `#[Assert\Choice(choices: [...])]` : valeur dans une liste
- `#[Assert\NotBlank]` : valeur non vide
- `#[Assert\Length(min: N, max: M)]` : longueur de chaîne

## Étape 2 : Créer le contrôleur

**Fichier** : `src/Infrastructure/Http/Controller/{Resource}/List{Resources}Controller.php`

### Template

```php
<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Controller\{Resource};

use App\Application\{Resource}\List{Resource}\List{Resource}Query;
use App\Application\{Resource}\List{Resource}\List{Resource}QueryHandler;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\Routing\Attribute\Route;

/**
 * {Resource} listing endpoints.
 */
final class List{Resources}Controller extends AbstractController
{
    public function __construct(
        private readonly List{Resource}QueryHandler $list{Resource}QueryHandler,
    ) {
    }

    /**
     * List {resources} with pagination and sorting.
     */
    #[Route('/api/{resources}', name: 'api_{resources}_list', methods: ['GET'])]
    public function __invoke(
        #[MapQueryString(
            validationFailedStatusCode: Response::HTTP_BAD_REQUEST
        )]
        List{Resources}QueryDTO $dto,
    ): JsonResponse {
        $query = new List{Resource}Query(
            page: $dto->page,
            itemsPerPage: $dto->itemsPerPage,
            orderBy: $dto->orderBy,
            sortField: $dto->sortField
        );

        $result = ($this->list{Resource}QueryHandler)($query);

        return $this->json([
            '{resources}' => array_map(fn($item) => [
                // Map domain model fields to JSON response
                'id' => $item->id,
                // Add other fields here based on your domain model
            ], $result['{resources}']),
            'pagination' => [
                'total' => $result['total'],
                'page' => $result['page'],
                'items_per_page' => $result['itemsPerPage'],
                'total_pages' => $result['totalPages'],
            ],
        ], Response::HTTP_OK);
    }
}
```

### Instructions de personnalisation

1. **Remplacer `{Resource}`** par le nom PascalCase singulier : `User`, `OAuthClient`
2. **Remplacer `{Resources}`** par le pluriel PascalCase : `Users`, `OAuthClients`
3. **Remplacer `{resource}`** par le nom en minuscule singulier : `user`, `oauth client`
4. **Remplacer `{resources}`** par le nom en minuscule pluriel : `users`, `oauth_clients`, `clients`
5. **Mapper les champs du modèle** dans le `array_map` selon les propriétés de votre modèle de domaine
6. **Adapter l'URL de la route** : `/api/users`, `/api/clients`, `/api/tokens`
7. **Adapter le nom de la route** : `api_users_list`, `api_clients_list`

### Mapping des champs (exemples)

**Pour User** :
```php
'users' => array_map(fn($user) => [
    'id' => $user->id,
    'email' => $user->email,
    'roles' => $user->roles,
    'is_2fa_enabled' => $user->is2faEnabled,
    'created_at' => $user->createdAt->format(\DateTimeInterface::ATOM),
    'updated_at' => $user->updatedAt?->format(\DateTimeInterface::ATOM),
], $result['users'])
```

**Pour OAuthClient** :
```php
'clients' => array_map(fn($client) => [
    'id' => $client->id,
    'client_id' => $client->clientId,
    'name' => $client->name,
    'redirect_uris' => $client->redirectUris,
    'grant_types' => $client->grantTypes,
    'created_at' => $client->createdAt->format(\DateTimeInterface::ATOM),
], $result['clients'])
```

**Pour des dates** : Toujours utiliser `->format(\DateTimeInterface::ATOM)` pour les `\DateTimeImmutable`

**Pour des valeurs nullables** : Utiliser l'opérateur null-safe `?->` : `$item->updatedAt?->format(\DateTimeInterface::ATOM)`

## Étape 3 : Vérifier la qualité du code

### Commandes à exécuter

```bash
# Appliquer le formatage du code
make apply-cs

# Vérifier l'analyse statique
make static-code-analysis
```

### Erreurs courantes et solutions

**Erreur PHPStan** : `Parameter #2 $default expects string|null, int given`
- **Solution** : Utiliser des chaînes pour les valeurs par défaut dans `$request->query->get('param', '1')` au lieu de `'1'`

**Erreur PHP-CS-Fixer** : Espacement des attributs
- **Solution** : Laisser PHP-CS-Fixer corriger automatiquement avec `make apply-cs`

**Erreur PHPStan** : Type mismatch dans le mapping
- **Solution** : Vérifier que les types retournés correspondent au format JSON attendu (string, int, bool, array)

## Étape 4 : Tester l'endpoint

### Requête de base

```bash
curl -X GET "http://localhost:8000/api/{resources}"
```

### Avec paramètres

```bash
curl -X GET "http://localhost:8000/api/{resources}?page=2&itemsPerPage=20&orderBy=desc&sortField=created_at"
```

### Réponse attendue (200 OK)

```json
{
  "{resources}": [
    {
      "id": "...",
      "field1": "...",
      "field2": "..."
    }
  ],
  "pagination": {
    "total": 100,
    "page": 2,
    "items_per_page": 20,
    "total_pages": 5
  }
}
```

### Réponse d'erreur de validation (400 Bad Request)

```json
{
  "type": "https://symfony.com/errors/validation",
  "title": "Validation Failed",
  "detail": "page: Page must be greater than 0.",
  "violations": [
    {
      "propertyPath": "page",
      "message": "Page must be greater than 0.",
      "code": "..."
    }
  ]
}
```

## Points clés de l'architecture

### Séparation des responsabilités

- **DTO (Infrastructure)** : Validation HTTP et mapping des query parameters
- **Query (Application)** : Logique de validation métier et structure des données
- **QueryHandler (Application)** : Orchestration et appel au repository
- **Controller (Infrastructure)** : Routing HTTP et transformation en JSON

### Avantages de MapQueryString

1. **Validation automatique** : Symfony valide les paramètres avant l'appel au contrôleur
2. **Messages d'erreur cohérents** : Format JSON standardisé pour les erreurs de validation
3. **Code plus propre** : Plus besoin de `$request->query->get()` manuel
4. **Type safety** : Les types sont garantis après validation
5. **Réutilisabilité** : Le DTO peut être réutilisé dans d'autres contextes

### Différence DTO vs Query

- **DTO** (`List{Resources}QueryDTO`) : Couche HTTP, validation des entrées utilisateur
- **Query** (`List{Resource}Query`) : Couche Application, logique métier et validation domaine

Le DTO valide les contraintes HTTP, puis les données sont passées à la Query qui peut ajouter des validations métier supplémentaires.

## Conventions de nommage

| Élément | Convention | Exemple |
|---------|------------|---------|
| Resource (singulier) | PascalCase | `User`, `OAuthClient`, `AccessToken` |
| Resources (pluriel) | PascalCase | `Users`, `OAuthClients`, `AccessTokens` |
| URL path | kebab-case/snake_case | `/api/users`, `/api/oauth-clients` |
| Route name | snake_case | `api_users_list`, `api_clients_list` |
| JSON keys | snake_case | `created_at`, `is_2fa_enabled` |
| Controller class | PascalCase + Controller | `ListUsersController` |
| DTO class | PascalCase + QueryDTO | `ListUsersQueryDTO` |
| Query class | PascalCase + Query | `ListUserQuery` |
| Handler class | PascalCase + QueryHandler | `ListUserQueryHandler` |

## Checklist de validation

- [ ] DTO créé avec contraintes de validation appropriées
- [ ] Contrôleur créé avec `MapQueryString`
- [ ] Mapping des champs du domaine vers JSON complet
- [ ] Route configurée avec bon path et nom
- [ ] Dates formatées avec `\DateTimeInterface::ATOM`
- [ ] Valeurs nullables gérées avec `?->`
- [ ] `make apply-cs` exécuté et passé
- [ ] `make static-code-analysis` exécuté et passé
- [ ] Endpoint testé manuellement avec curl
- [ ] Validation des erreurs testée (page=0, itemsPerPage=101, etc.)

## Exemple complet : User

Voir l'implémentation de référence :
- DTO : `src/Infrastructure/Http/Controller/User/ListUsersQueryDTO.php`
- Controller : `src/Infrastructure/Http/Controller/User/ListUsersController.php`
- Query : `src/Application/User/ListUser/ListUserQuery.php`
- Handler : `src/Application/User/ListUser/ListUserQueryHandler.php`
