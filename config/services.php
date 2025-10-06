<?php

declare(strict_types=1);

use App\Application\AccessToken\GrantHandler\GrantHandlerDispatcher;
use App\Application\AccessToken\GrantHandler\GrantHandlerInterface;
use App\Infrastructure\Audit\AuditLogger;
use App\Infrastructure\Audit\EventSubscriber\OAuth2ExceptionSubscriber;
use App\Infrastructure\Cli\Command\AuditLogCleanupCommand;
use App\OAuth2\Service\JwtTokenGenerator;
use App\Service\PrivateKeyEncryptionService;
use App\Service\RateLimiter\RateLimiterService;
use App\Service\TokenHasher;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;
use function Symfony\Component\DependencyInjection\Loader\Configurator\tagged_iterator;

use Symfony\Component\HttpFoundation\Session\Storage\Handler\RedisSessionHandler;

return static function (ContainerConfigurator $containerConfigurator): void {
    $parameters = $containerConfigurator->parameters();

    $parameters->set('oauth2.error_uri_base', '%env(OAUTH2_ERROR_URI_BASE)%');

    $parameters->set('oauth2.issuer', '%env(OAUTH2_ISSUER)%');

    $parameters->set('oauth2.access_token_ttl', '%env(int:ACCESS_TOKEN_TTL)%');
    $parameters->set('oauth2.client_credentials.access_token_ttl', '%env(int:CLIENT_CREDENTIALS_ACCESS_TOKEN_TTL)%');

    $services = $containerConfigurator->services();

    $services->defaults()
        ->autowire()
        ->autoconfigure();

    $services->load('App\\', __DIR__ . '/../src/')
        ->exclude([
            __DIR__ . '/../src/Model/',
            __DIR__ . '/../src/Domain/*/{Model,DTO,Exception}/',
            __DIR__ . '/../src/Infrastructure/Security/User/',
        ]);

    $services->set(TokenHasher::class)
        ->arg('$secret', '%env(APP_SECRET)%');

    $services->set(RedisSessionHandler::class)
        ->args([
            service('redis.session_connection'),
        ]);

    $services->set('redis.session_connection', Redis::class)
        ->call('connect', [
            '%env(REDIS_HOST)%',
            '%env(int:REDIS_PORT)%',
        ]);

    $services->set(RateLimiterService::class)
        ->arg('$oauthTokenLimiter', service('limiter.oauth_token'))
        ->arg('$loginLimiter', service('limiter.login'))
        ->arg('$globalIpLimiter', service('limiter.global_ip'))
        ->arg('$whitelistedIpsString', '%env(RATE_LIMITER_WHITELIST_IPS)%');

    $services->set(AuditLogger::class)
        ->arg('$auditLogger', service('monolog.logger.audit'));

    $services->set(AuditLogCleanupCommand::class)
        ->arg('$defaultRetentionDays', '%env(int:AUDIT_LOG_RETENTION_DAYS)%');

    $services->set(OAuth2ExceptionSubscriber::class)
        ->arg('$errorUriBase', '%oauth2.error_uri_base%');

    $services->set(PrivateKeyEncryptionService::class)
        ->arg('$encryptionKey', '%env(PRIVATE_KEY_ENCRYPTION_KEY)%');

    $services->set(JwtTokenGenerator::class)
        ->arg('$issuer', '%oauth2.issuer%');

    $services->instanceof(GrantHandlerInterface::class)
        ->tag('oauth2.grant_handler');

    $services->set(GrantHandlerDispatcher::class)
        ->arg('$handlers', tagged_iterator('oauth2.grant_handler'));

    $services->set(App\Application\AccessToken\GrantHandler\ClientCredentialsGrantHandler::class)
        ->arg('$accessTokenTtl', '%oauth2.client_credentials.access_token_ttl%');
};
