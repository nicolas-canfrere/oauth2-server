<?php

declare(strict_types=1);

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $containerConfigurator): void {
    $containerConfigurator->extension('monolog', [
        'channels' => [
            'deprecation',
            'audit',
        ],
    ]);
    if ('dev' === $containerConfigurator->env()) {
        $containerConfigurator->extension('monolog', [
            'handlers' => [
                'main' => [
                    'type' => 'stream',
                    'path' => '%kernel.logs_dir%/%kernel.environment%.log',
                    'level' => 'debug',
                    'channels' => [
                        '!event',
                        '!audit',
                    ],
                ],
                'audit' => [
                    'type' => 'stream',
                    'path' => '%kernel.logs_dir%/audit.log',
                    'level' => 'info',
                    'channels' => [
                        'audit',
                    ],
                    'formatter' => 'monolog.formatter.json',
                ],
                'console' => [
                    'type' => 'console',
                    'process_psr_3_messages' => false,
                    'channels' => [
                        '!event',
                        '!doctrine',
                        '!console',
                        '!audit',
                    ],
                ],
            ],
        ]);
    }
    if ('test' === $containerConfigurator->env()) {
        $containerConfigurator->extension('monolog', [
            'handlers' => [
                'main' => [
                    'type' => 'fingers_crossed',
                    'action_level' => 'error',
                    'handler' => 'nested',
                    'excluded_http_codes' => [
                        404,
                        405,
                    ],
                    'channels' => [
                        '!event',
                        '!audit',
                    ],
                ],
                'audit' => [
                    'type' => 'stream',
                    'path' => '%kernel.logs_dir%/audit.log',
                    'level' => 'info',
                    'channels' => [
                        'audit',
                    ],
                    'formatter' => 'monolog.formatter.json',
                ],
                'nested' => [
                    'type' => 'stream',
                    'path' => '%kernel.logs_dir%/%kernel.environment%.log',
                    'level' => 'debug',
                ],
            ],
        ]);
    }
    if ('prod' === $containerConfigurator->env()) {
        $containerConfigurator->extension('monolog', [
            'handlers' => [
                'main' => [
                    'type' => 'fingers_crossed',
                    'action_level' => 'error',
                    'handler' => 'nested',
                    'excluded_http_codes' => [
                        404,
                        405,
                    ],
                    'buffer_size' => 50,
                    'channels' => [
                        '!event',
                        '!audit',
                    ],
                ],
                'audit' => [
                    'type' => 'stream',
                    'path' => 'php://stderr',
                    'level' => 'info',
                    'channels' => [
                        'audit',
                    ],
                    'formatter' => 'monolog.formatter.json',
                ],
                'nested' => [
                    'type' => 'stream',
                    'path' => 'php://stderr',
                    'level' => 'debug',
                    'formatter' => 'monolog.formatter.json',
                ],
                'console' => [
                    'type' => 'console',
                    'process_psr_3_messages' => false,
                    'channels' => [
                        '!event',
                        '!doctrine',
                        '!audit',
                    ],
                ],
                'deprecation' => [
                    'type' => 'stream',
                    'channels' => [
                        'deprecation',
                    ],
                    'path' => 'php://stderr',
                    'formatter' => 'monolog.formatter.json',
                ],
            ],
        ]);
    }
};
