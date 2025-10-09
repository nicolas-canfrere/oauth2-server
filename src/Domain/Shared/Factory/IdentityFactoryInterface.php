<?php

declare(strict_types=1);

namespace App\Domain\Shared\Factory;

interface IdentityFactoryInterface
{
    public function generate(): string;
}
