<?php

declare(strict_types=1);

namespace App\Security;

use App\Repository\UserRepositoryInterface;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

/**
 * User Provider for Symfony Security.
 *
 * This provider loads users from the database using UserRepository
 * and converts them to SecurityUser instances for authentication.
 *
 * @implements UserProviderInterface<SecurityUser>
 */
final readonly class UserProvider implements UserProviderInterface
{
    public function __construct(
        private UserRepositoryInterface $userRepository,
    ) {
    }

    /**
     * {@inheritDoc}
     */
    public function loadUserByIdentifier(string $identifier): UserInterface
    {
        $user = $this->userRepository->findByEmail($identifier);

        if (null === $user) {
            throw new UserNotFoundException(sprintf('User "%s" not found.', $identifier));
        }

        return SecurityUser::fromUser($user);
    }

    /**
     * {@inheritDoc}
     */
    public function refreshUser(UserInterface $user): UserInterface
    {
        if (!$user instanceof SecurityUser) {
            throw new \InvalidArgumentException(sprintf('Instances of "%s" are not supported.', $user::class));
        }

        return $this->loadUserByIdentifier($user->getUserIdentifier());
    }

    /**
     * {@inheritDoc}
     */
    public function supportsClass(string $class): bool
    {
        return SecurityUser::class === $class || is_subclass_of($class, SecurityUser::class);
    }
}
