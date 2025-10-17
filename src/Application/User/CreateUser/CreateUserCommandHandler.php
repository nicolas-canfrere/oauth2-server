<?php

declare(strict_types=1);

namespace App\Application\User\CreateUser;

use App\Domain\Shared\Factory\IdentityFactoryInterface;
use App\Domain\User\Model\User;
use App\Domain\User\Repository\UserRepositoryInterface;
use App\Domain\User\Service\UserPasswordHasherInterface;
use App\Domain\User\UserAlreadyExistsException;

final readonly class CreateUserCommandHandler
{
    public function __construct(
        private UserPasswordHasherInterface $passwordHasher,
        private IdentityFactoryInterface $identityFactory,
        private UserRepositoryInterface $userRepository,
    ) {
    }

    /**
     * @return string Newly created user ID
     */
    public function __invoke(CreateUserCommand $command): string
    {
        $normalizedEmail = strtolower(trim($command->email));
        if ('' === $normalizedEmail) {
            throw new \InvalidArgumentException('Email cannot be empty.');
        }

        if (!filter_var($normalizedEmail, FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException(sprintf('Email "%s" is not valid.', $command->email));
        }

        if ('' === $command->plainPassword) {
            throw new \InvalidArgumentException('Password cannot be empty.');
        }

        if (null !== $this->userRepository->findByEmail($normalizedEmail)) {
            throw new UserAlreadyExistsException(sprintf('User with email "%s" already exists.', $normalizedEmail));
        }

        $passwordHash = $this->passwordHasher->hash($command->plainPassword);

        $userId = $command->userId ?? $this->identityFactory->generate();
        $now = new \DateTimeImmutable();

        $roles = array_values(array_filter(
            array_map(
                static fn(mixed $role): string => strtoupper(trim($role)),
                $command->roles
            ),
            static fn(string $role): bool => '' !== $role
        ));

        if ([] === $roles) {
            $roles = ['ROLE_USER'];
        }

        $totpSecret = $command->isTwoFactorEnabled && null !== $command->totpSecret
            ? trim($command->totpSecret)
            : null;

        $user = new User(
            id: $userId,
            email: $normalizedEmail,
            passwordHash: $passwordHash,
            is2faEnabled: $command->isTwoFactorEnabled,
            totpSecret: $totpSecret,
            roles: $roles,
            createdAt: $now,
        );

        $this->userRepository->create($user);

        return $userId;
    }
}
