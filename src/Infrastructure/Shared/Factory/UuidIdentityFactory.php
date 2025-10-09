<?php

declare(strict_types=1);

namespace App\Infrastructure\Shared\Factory;

use App\Domain\Shared\Factory\IdentityFactoryInterface;
use Symfony\Component\Uid\Uuid;

final class UuidIdentityFactory implements IdentityFactoryInterface
{
    public function generate(): string
    {
        return Uuid::v4()->toString();
    }
}
