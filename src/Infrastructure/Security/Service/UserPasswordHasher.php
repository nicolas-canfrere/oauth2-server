<?php

declare(strict_types=1);

namespace App\Infrastructure\Security\Service;

use App\Domain\User\Service\UserPasswordHasherInterface;
use App\Infrastructure\Security\User\SecurityUser;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface as SymfonyUserPasswordHasherInterface;

final readonly class UserPasswordHasher implements UserPasswordHasherInterface
{
    public function __construct(
        private SymfonyUserPasswordHasherInterface $userPasswordHasher,
    ) {
    }

    public function hash(string $plainPassword): string
    {
        if ('' === $plainPassword) {
            throw new \InvalidArgumentException('Cannot hash empty password.');
        }

        /** @var string|false $hash */
        $hash = $this->userPasswordHasher->hashPassword($this->createSecurityUser(), $plainPassword);
        if (false === $hash) {
            throw new \RuntimeException('Unable to hash user password with the configured algorithm.');
        }

        return $hash;
    }

    private function createSecurityUser(): SecurityUser
    {
        $securityUserRef = new \ReflectionClass(SecurityUser::class);

        return $securityUserRef->newInstanceWithoutConstructor();
    }
}
