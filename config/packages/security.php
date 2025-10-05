<?php

declare(strict_types=1);

use App\Infrastructure\Security\OAuth2ClientAuthenticator;
use App\Infrastructure\Security\UserProvider;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;

return static function (ContainerConfigurator $containerConfigurator): void {
    $containerConfigurator->extension('security', [
        'password_hashers' => [
            PasswordAuthenticatedUserInterface::class => [
                'algorithm' => 'bcrypt',
                'cost' => 12,
            ],
        ],
        'providers' => [
            'app_user_provider' => [
                'id' => UserProvider::class,
            ],
        ],
        'firewalls' => [
            'dev' => [
                'pattern' => '^/(_(profiler|wdt)|css|images|js)/',
                'security' => false,
            ],
            'oauth' => [
                'pattern' => '^/oauth',
                'stateless' => true,
                'custom_authenticators' => [
                    OAuth2ClientAuthenticator::class,
                ],
            ],
            'admin' => [
                'pattern' => '^/admin',
                'lazy' => true,
                'stateless' => false,
                'provider' => 'app_user_provider',
                'json_login' => [
                    'check_path' => 'admin_login',
                    'username_path' => 'email',
                    'password_path' => 'password',
                ],
                'logout' => [
                    'path' => 'admin_logout',
                ],
            ],
            'api' => [
                'pattern' => '^/api',
                'stateless' => true,
                'provider' => 'app_user_provider',
            ],
            'main' => [
                'lazy' => true,
                'provider' => 'app_user_provider',
            ],
        ],
        'access_control' => [
            [
                'path' => '^/oauth/token',
                'roles' => 'PUBLIC_ACCESS',
            ],
            [
                'path' => '^/oauth/authorize',
                'roles' => 'ROLE_USER',
            ],
            [
                'path' => '^/admin/login',
                'roles' => 'PUBLIC_ACCESS',
            ],
            [
                'path' => '^/admin',
                'roles' => 'ROLE_ADMIN',
            ],
            [
                'path' => '^/api',
                'roles' => 'ROLE_USER',
            ],
        ],
    ]);
    if ('test' === $containerConfigurator->env()) {
        $containerConfigurator->extension('security', [
            'password_hashers' => [
                PasswordAuthenticatedUserInterface::class => [
                    'algorithm' => 'auto',
                    'cost' => 4,
                    'time_cost' => 3,
                    'memory_cost' => 10,
                ],
            ],
        ]);
    }
};
