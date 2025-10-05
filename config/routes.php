<?php

declare(strict_types=1);

use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

return static function (RoutingConfigurator $routingConfigurator): void {
    $routingConfigurator->import([
        'path' => '../src/Infrastructure/Http/Controller/',
        'namespace' => 'App\Infrastructure\Http\Controller',
    ], 'attribute');
};
