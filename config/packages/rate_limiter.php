<?php

declare(strict_types=1);

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $containerConfigurator): void {
    $containerConfigurator->extension('framework', [
        'rate_limiter' => [
            'oauth_token' => [
                'policy' => 'sliding_window',
                'limit' => 20,
                'interval' => '1 minute',
                'lock_factory' => 'lock.rate_limiter.factory',
            ],
            'login' => [
                'policy' => 'token_bucket',
                'limit' => 5,
                'rate' => [
                    'interval' => '5 minutes',
                    'amount' => 5,
                ],
                'lock_factory' => 'lock.rate_limiter.factory',
            ],
            'global_ip' => [
                'policy' => 'sliding_window',
                'limit' => 100,
                'interval' => '1 minute',
                'lock_factory' => 'lock.rate_limiter.factory',
            ],
        ],
        'lock' => [
            'rate_limiter' => [
                'connection' => '%env(REDIS_DSN)%',
            ],
        ],
        'cache' => [
            'pools' => [
                'cache.rate_limiter' => [
                    'adapter' => 'cache.adapter.redis',
                    'provider' => '%env(REDIS_DSN)%',
                ],
            ],
        ],
    ]);
    if ('test' === $containerConfigurator->env()) {
        $containerConfigurator->extension('framework', [
            'rate_limiter' => [
                'oauth_token' => [
                    'policy' => 'sliding_window',
                    'limit' => 999999,
                    'interval' => '1 minute',
                ],
                'login' => [
                    'policy' => 'token_bucket',
                    'limit' => 999999,
                    'rate' => [
                        'interval' => '1 minute',
                        'amount' => 999999,
                    ],
                ],
                'global_ip' => [
                    'policy' => 'sliding_window',
                    'limit' => 999999,
                    'interval' => '1 minute',
                ],
            ],
        ]);
    }
};
