# Intégration ClientAuthenticator avec Symfony Security

## Vue d'ensemble

Le `ClientAuthenticator` a été intégré avec le système de sécurité Symfony pour protéger le endpoint `/oauth/token` avec l'authentification OAuth2 client.

## Architecture

### Composants créés

1. **`OAuth2ClientAuthenticator`** (`src/Security/OAuth2ClientAuthenticator.php`)
   - Symfony Security Authenticator personnalisé
   - Implémente `AbstractAuthenticator`
   - Utilise `ClientAuthenticatorInterface` en interne (qui utilise `ClientRepository`)
   - Supporte uniquement le endpoint `/oauth/token`
   - Utilise `SelfValidatingPassport` (pas besoin de UserProvider)

2. **`OAuth2ClientUser`** (`src/Security/OAuth2ClientUser.php`)
   - Implémente `UserInterface` de Symfony
   - Représente un client OAuth2 comme utilisateur Symfony Security
   - Role: `ROLE_OAUTH2_CLIENT`

## Configuration Symfony Security

### Firewall OAuth2

```yaml
# config/packages/security.yaml
security:
    firewalls:
        oauth:
            pattern: ^/oauth
            stateless: true
            custom_authenticators:
                - App\Security\OAuth2ClientAuthenticator

    access_control:
        - { path: ^/oauth/token, roles: PUBLIC_ACCESS }
```

**Note**: Pas besoin de `provider` car le `SelfValidatingPassport` gère l'authentification complète.
Le `ClientAuthenticator` charge directement le client via `ClientRepository`.

### Fonctionnement

1. **Request arrive sur `/oauth/token`**
2. **`OAuth2ClientAuthenticator::supports()`** retourne `true`
3. **`OAuth2ClientAuthenticator::authenticate()`** est appelé:
   - Utilise `ClientAuthenticator` pour authentifier le client
   - Crée un `SelfValidatingPassport` avec `OAuth2ClientUser`
4. **En cas de succès**:
   - Token Symfony Security créé avec `OAuth2ClientUser`
   - Request continue vers le contrôleur
   - Le contrôleur peut accéder au client via `$this->getUser()`
5. **En cas d'échec**:
   - Retourne erreur JSON OAuth2: `{"error": "invalid_client", ...}`

## Utilisation dans un contrôleur

```php
#[Route('/oauth/token', methods: ['POST'])]
public function token(Request $request): JsonResponse
{
    // Le client est déjà authentifié par le firewall
    $user = $this->getUser();

    if ($user instanceof OAuth2ClientUser) {
        $client = $user->getClient();

        // Le client est authentifié, traiter la requête token
        // ...
    }

    // ...
}
```

## Méthodes d'authentification supportées

Le `ClientAuthenticator` supporte automatiquement:

### 1. HTTP Basic Authentication (Recommandé)
```http
POST /oauth/token HTTP/1.1
Authorization: Basic Y2xpZW50X2lkOmNsaWVudF9zZWNyZXQ=
Content-Type: application/x-www-form-urlencoded

grant_type=authorization_code&code=...
```

### 2. POST Body Parameters
```http
POST /oauth/token HTTP/1.1
Content-Type: application/x-www-form-urlencoded

grant_type=authorization_code&code=...&client_id=my-client&client_secret=secret
```

### 3. Public Clients (client_id seul)
```http
POST /oauth/token HTTP/1.1
Content-Type: application/x-www-form-urlencoded

grant_type=authorization_code&code=...&client_id=public-client
```

## Sécurité

- ✅ Vérification bcrypt des secrets
- ✅ Protection timing attacks (hash_equals)
- ✅ Dummy hash pour clients inexistants
- ✅ Logging complet des tentatives
- ✅ Support clients confidentiels ET publics

## Tests

L'intégration est automatiquement testée via:
- Tests unitaires de `ClientAuthenticator`
- Tests d'intégration avec base de données
- Configuration firewall validée par Symfony

## Alternative : Sans Symfony Security

Si vous préférez **ne pas** utiliser Symfony Security pour les clients OAuth2, vous pouvez:

1. Désactiver le firewall OAuth2
2. Utiliser `ClientAuthenticator` directement dans votre contrôleur:

```php
#[Route('/oauth/token', methods: ['POST'])]
public function token(
    Request $request,
    ClientAuthenticatorInterface $clientAuth
): JsonResponse {
    $client = $clientAuth->authenticate($request);

    if (null === $client) {
        return new JsonResponse([
            'error' => 'invalid_client',
            'error_description' => 'Client authentication failed',
        ], Response::HTTP_UNAUTHORIZED);
    }

    // Client authentifié, continuer...
}
```

## Avantages de l'intégration Symfony Security

- ✅ Authentification automatique avant le contrôleur
- ✅ Gestion standardisée des erreurs
- ✅ Intégration avec le système de rôles Symfony
- ✅ Support des event subscribers Symfony Security
- ✅ Logging et audit via les events Symfony
- ✅ Compatible avec les outils de debug Symfony (profiler, etc.)

## Intégration avec AuditLogger

L'authentification OAuth2 client est automatiquement loggée via `OAuth2AuthenticationSubscriber`.

### Fonctionnement

Le subscriber écoute les événements Symfony Security et log automatiquement:

**Événement `LoginSuccessEvent`**:
- Filtre: uniquement les utilisateurs de type `OAuth2ClientUser`
- Log: `AuditEventTypeEnum::CLIENT_AUTHENTICATED`
- Contexte:
  - `firewall`: Nom du firewall (oauth)
  - `authentication_method`: http_basic | post_body | public_client
  - `client_type`: confidential | public
  - IP et User-Agent

**Événement `LoginFailureEvent`**:
- Filtre: uniquement le firewall `oauth`
- Log: `AuditEventTypeEnum::CLIENT_AUTHENTICATION_FAILED`
- Extraction du `client_id` depuis:
  - POST body (`client_id`)
  - HTTP Basic Auth (décodage base64)
- Contexte:
  - `firewall`: oauth
  - `authentication_method`: http_basic | post_body | public_client | unknown
  - `reason`: Message d'erreur
  - IP et User-Agent

### Exemples de logs

**Succès:**
```json
{
  "event_type": "auth.client.authenticated",
  "level": "info",
  "message": "OAuth2 client \"my-client\" authenticated successfully",
  "context": {
    "firewall": "oauth",
    "authentication_method": "http_basic",
    "client_type": "confidential"
  },
  "client_id": "my-client",
  "ip_address": "192.168.1.100",
  "user_agent": "curl/7.68.0",
  "timestamp": "2025-10-05T14:30:00+00:00"
}
```

**Échec:**
```json
{
  "event_type": "auth.client.failed",
  "level": "warning",
  "message": "OAuth2 client authentication failed: Invalid client credentials",
  "context": {
    "firewall": "oauth",
    "authentication_method": "http_basic",
    "reason": "Invalid client credentials"
  },
  "client_id": "unknown-client",
  "ip_address": "192.168.1.100",
  "user_agent": "curl/7.68.0",
  "timestamp": "2025-10-05T14:30:00+00:00"
}
```

### Traçabilité complète

Chaque requête OAuth2 génère automatiquement:
1. **Log Monolog** (JSON Lines) → `var/log/audit.log` (dev/test) ou `php://stderr` (production)
2. **Enregistrement base de données** → Table `oauth_audit_logs`

Cela permet:
- Analyse en temps réel via logs
- Requêtes historiques via base de données
- Détection d'anomalies (force brute, credential stuffing)
- Conformité réglementaire (RGPD, PCI-DSS)