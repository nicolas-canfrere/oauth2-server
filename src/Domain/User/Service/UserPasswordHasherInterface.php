<?php

declare(strict_types=1);

namespace App\Domain\User\Service;

interface UserPasswordHasherInterface
{
    public function hash(string $plainPassword): string;
}
