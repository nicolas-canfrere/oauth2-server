# GrantHandlerInterface - Documentation d'implémentation

## Vue d'ensemble

L'interface `GrantHandlerInterface` définit le contrat pour implémenter des handlers OAuth2 grant types selon le pattern Strategy. Cette interface permet de gérer différents types de grants OAuth2 (authorization_code, client_credentials, refresh_token, etc.) de manière extensible et modulaire.

## Localisation

- **Interface** : `src/OAuth2/GrantHandler/GrantHandlerInterface.php`
- **DTO** : `src/DTO/TokenResponseDTO.php`
- **Tests** : `tests/OAuth2/GrantHandler/GrantHandlerInterfaceTest.php`

## Architecture

### Pattern Strategy

L'architecture utilise le pattern Strategy pour permettre :
- L'ajout de nouveaux grant types sans modifier le code existant (Open/Closed Principle)
- La sélection dynamique du handler approprié via la méthode `supports()`
- Une séparation claire des responsabilités entre différents grant types

### Structure des classes

```
App\OAuth2\GrantHandler\
├── GrantHandlerInterface          # Interface du contrat
└── [Future implementations]
    ├── AuthorizationCodeGrantHandler
    ├── ClientCredentialsGrantHandler
    └── RefreshTokenGrantHandler

App\DTO\
└── TokenResponseDTO               # DTO pour la réponse OAuth2
```

## Contrat de l'interface

### Méthode `supports(string $grantType): bool`

**Objectif** : Déterminer si le handler peut traiter un type de grant spécifique.

**Paramètres** :
- `$grantType` : Le type de grant OAuth2 (ex: "authorization_code", "client_credentials")

**Retour** :
- `true` si le handler supporte ce grant type
- `false` sinon

**Exemple d'implémentation** :
```php
public function supports(string $grantType): bool
{
    return 'authorization_code' === $grantType;
}
```

### Méthode `handle(array $parameters): TokenResponseDTO`

**Objectif** : Traiter la demande de token et retourner les tokens générés.

**Paramètres** :
- `$parameters` : Tableau associatif contenant les paramètres de la requête OAuth2

**Paramètres typiques** :
- `grant_type` : Le type de grant (REQUIS)
- `client_id` : Identifiant du client (REQUIS pour clients publics)
- `client_secret` : Secret du client (REQUIS pour clients confidentiels)
- `code` : Code d'autorisation (pour authorization_code)
- `redirect_uri` : URI de redirection (pour authorization_code)
- `code_verifier` : Vérificateur PKCE (pour authorization_code avec PKCE)
- `refresh_token` : Refresh token (pour refresh_token grant)
- `scope` : Scopes demandés (OPTIONNEL, séparés par des espaces)

**Retour** :
- Instance de `TokenResponseDTO` contenant :
  - `accessToken` : Le token d'accès généré
  - `tokenType` : Type de token (généralement "Bearer")
  - `expiresIn` : Durée de vie en secondes
  - `refreshToken` : Token de rafraîchissement (optionnel)
  - `scope` : Scopes accordés (optionnel)
  - `additionalData` : Données supplémentaires (ex: id_token pour OpenID Connect)

**Exceptions lancées** :
- `InvalidRequestException` : Paramètres manquants ou malformés
- `InvalidGrantException` : Grant invalide, expiré ou révoqué
- `OAuth2Exception` : Autres erreurs OAuth2 (invalid_client, unauthorized_client, etc.)

**Exemple d'implémentation** :
```php
public function handle(array $parameters): TokenResponseDTO
{
    // 1. Valider les paramètres requis
    if (!isset($parameters['code'])) {
        throw new InvalidRequestException('Missing required parameter: code');
    }

    // 2. Authentifier le client
    $client = $this->authenticateClient($parameters);

    // 3. Valider le code d'autorisation
    $authCode = $this->validateAuthorizationCode($parameters['code']);
    if (!$authCode || $authCode->isExpired()) {
        throw new InvalidGrantException('Invalid or expired authorization code');
    }

    // 4. Générer les tokens
    $accessToken = $this->generateAccessToken($client, $authCode);
    $refreshToken = $this->generateRefreshToken($client);

    // 5. Retourner le DTO
    return new TokenResponseDTO(
        accessToken: $accessToken,
        tokenType: 'Bearer',
        expiresIn: 3600,
        refreshToken: $refreshToken,
        scope: $authCode->getScope(),
    );
}
```

## TokenResponseDTO

### Structure

```php
final readonly class TokenResponseDTO
{
    public function __construct(
        public string $accessToken,      // Token d'accès (REQUIS)
        public string $tokenType,        // Type de token (REQUIS)
        public int $expiresIn,          // Durée de vie (REQUIS)
        public ?string $refreshToken = null,  // Refresh token (OPTIONNEL)
        public ?string $scope = null,         // Scopes (OPTIONNEL)
        public array $additionalData = [],    // Données additionnelles (OPTIONNEL)
    ) {}
}
```

### Méthode `toArray(): array<string, mixed>`

Convertit le DTO en tableau associatif conforme à la RFC 6749 Section 5.1.

**Exemple de sortie** :
```php
[
    'access_token' => 'eyJhbGciOiJSUzI1NiJ9...',
    'token_type' => 'Bearer',
    'expires_in' => 3600,
    'refresh_token' => 'tGzv3JOkF0XG5Qx2TlKWIA',
    'scope' => 'user:read user:write',
]
```

## Dispatcher de Grant Handlers

Pour utiliser les handlers, créez un dispatcher qui sélectionne le bon handler :

```php
final readonly class GrantHandlerDispatcher
{
    /**
     * @param iterable<GrantHandlerInterface> $handlers
     */
    public function __construct(
        private iterable $handlers,
    ) {}

    public function dispatch(array $parameters): TokenResponseDTO
    {
        $grantType = $parameters['grant_type'] ?? throw new InvalidRequestException('Missing grant_type');

        foreach ($this->handlers as $handler) {
            if ($handler->supports($grantType)) {
                return $handler->handle($parameters);
            }
        }

        throw new UnsupportedGrantTypeException(
            sprintf('Grant type "%s" is not supported', $grantType)
        );
    }
}
```

## Configuration Symfony

Enregistrez les handlers comme services avec auto-wiring :

```yaml
# config/services.yaml
services:
    # Grant Handlers
    App\OAuth2\GrantHandler\AuthorizationCodeGrantHandler:
        tags: ['oauth2.grant_handler']

    App\OAuth2\GrantHandler\ClientCredentialsGrantHandler:
        tags: ['oauth2.grant_handler']

    App\OAuth2\GrantHandler\RefreshTokenGrantHandler:
        tags: ['oauth2.grant_handler']

    # Dispatcher
    App\OAuth2\GrantHandler\GrantHandlerDispatcher:
        arguments:
            $handlers: !tagged_iterator oauth2.grant_handler
```

## Exemple complet d'implémentation

### Authorization Code Grant Handler

```php
<?php

declare(strict_types=1);

namespace App\OAuth2\GrantHandler;

use App\Application\AccessToken\Exception\InvalidRequestException;use App\Application\AccessToken\GrantHandler\GrantHandlerInterface;use App\DTO\TokenResponseDTO;use App\OAuth2\Exception\InvalidGrantException;use App\Repository\AuthorizationCodeRepositoryInterface;use App\Service\TokenGenerator;

final readonly class AuthorizationCodeGrantHandler implements GrantHandlerInterface
{
    public function __construct(
        private AuthorizationCodeRepositoryInterface $authCodeRepository,
        private TokenGenerator $tokenGenerator,
    ) {}

    public function supports(string $grantType): bool
    {
        return 'authorization_code' === $grantType;
    }

    public function handle(array $parameters): TokenResponseDTO
    {
        // Validation des paramètres
        $code = $parameters['code'] ?? throw new InvalidRequestException('Missing parameter: code');
        $clientId = $parameters['client_id'] ?? throw new InvalidRequestException('Missing parameter: client_id');

        // Récupération et validation du code d'autorisation
        $authCode = $this->authCodeRepository->findByCode($code);
        if (!$authCode || $authCode->isExpired()) {
            throw new InvalidGrantException('Invalid or expired authorization code');
        }

        // Vérification du client
        if ($authCode->getClientId() !== $clientId) {
            throw new InvalidGrantException('Authorization code was issued to another client');
        }

        // Génération des tokens
        $accessToken = $this->tokenGenerator->generateAccessToken(
            userId: $authCode->getUserId(),
            clientId: $clientId,
            scopes: $authCode->getScopes(),
        );

        $refreshToken = $this->tokenGenerator->generateRefreshToken(
            userId: $authCode->getUserId(),
            clientId: $clientId,
        );

        // Révocation du code d'autorisation (usage unique)
        $this->authCodeRepository->revoke($code);

        return new TokenResponseDTO(
            accessToken: $accessToken,
            tokenType: 'Bearer',
            expiresIn: 3600,
            refreshToken: $refreshToken,
            scope: implode(' ', $authCode->getScopes()),
        );
    }
}
```

## Tests

Les tests vérifient :

1. **Existence de l'interface** : Validation que l'interface existe
2. **Signatures des méthodes** : Vérification des types et paramètres via Reflection
3. **Implémentations concrètes** : Test du comportement avec des implémentations anonymes
4. **Gestion des exceptions** : Validation que les handlers peuvent lancer les exceptions appropriées
5. **Pattern Strategy** : Test de plusieurs handlers avec différents grant types

### Exemple de test

```php
public function testConcreteImplementationSupports(): void
{
    $handler = new class implements GrantHandlerInterface {
        public function supports(string $grantType): bool
        {
            return 'authorization_code' === $grantType;
        }

        public function handle(array $parameters): TokenResponseDTO
        {
            return new TokenResponseDTO(
                accessToken: 'test_token',
                tokenType: 'Bearer',
                expiresIn: 3600,
            );
        }
    };

    self::assertTrue($handler->supports('authorization_code'));
    self::assertFalse($handler->supports('client_credentials'));
}
```

## Conformité RFC

Cette implémentation respecte les standards suivants :

- **RFC 6749 Section 4** : Authorization Grant (types de grants)
- **RFC 6749 Section 5.1** : Successful Response (format de la réponse token)
- **RFC 6749 Section 5.2** : Error Response (format des erreurs)

## Prochaines étapes

1. Implémenter `AuthorizationCodeGrantHandler` (tâche 2.1.2)
2. Implémenter la validation PKCE (tâche 2.1.3)
3. Implémenter `ClientCredentialsGrantHandler` (tâche 5.1.1)
4. Implémenter `RefreshTokenGrantHandler` (tâche 5.2.1)

## Références

- [RFC 6749 - OAuth 2.0 Authorization Framework](https://datatracker.ietf.org/doc/html/rfc6749)
- [RFC 6749 Section 4 - Obtaining Authorization](https://datatracker.ietf.org/doc/html/rfc6749#section-4)
- [RFC 6749 Section 5.1 - Successful Response](https://datatracker.ietf.org/doc/html/rfc6749#section-5.1)
