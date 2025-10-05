<?php

declare(strict_types=1);

use App\Infrastructure\Persistance\Doctrine\Repository\AuthorizationCodeRepository;
use App\Infrastructure\Persistance\Doctrine\Repository\RefreshTokenRepository;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();

    $services->defaults()
        ->autowire()
        ->autoconfigure();

    $services->set(RefreshTokenRepository::class)
        ->public();

    $services->set(AuthorizationCodeRepository::class)
        ->public();
};
