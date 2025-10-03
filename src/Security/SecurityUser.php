<?php

declare(strict_types=1);

namespace App\Security;

use App\Model\User;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Security User wrapper for Symfony Security integration.
 *
 * This class wraps the App\Model\User readonly DTO to provide
 * Symfony Security UserInterface implementation while maintaining
 * separation of concerns between persistence and authentication layers.
 */
final readonly class SecurityUser implements UserInterface, PasswordAuthenticatedUserInterface
{
    /**
     * @param array<string> $roles
     */
    public function __construct(
        private string $userIdentifier,
        private string $passwordHash,
        private array $roles,
        private string $userId,
    ) {
        if ('' === $userIdentifier) {
            throw new \RuntimeException('User identifier cannot be empty');
        }
    }

    /**
     * Create SecurityUser from User model.
     */
    public static function fromUser(User $user): self
    {
        return new self(
            userIdentifier: $user->email,
            passwordHash: $user->passwordHash,
            roles: $user->roles,
            userId: $user->id,
        );
    }

    /**
     * {@inheritDoc}
     *
     * @return non-empty-string User email address
     */
    public function getUserIdentifier(): string
    {
        /** @var non-empty-string $userIdentifier */
        $userIdentifier = $this->userIdentifier;

        return $userIdentifier;
    }

    /**
     * {@inheritDoc}
     *
     * @return array<string>
     */
    public function getRoles(): array
    {
        $roles = $this->roles;

        // Guarantee every user at least has ROLE_USER
        if (!\in_array('ROLE_USER', $roles, true)) {
            $roles[] = 'ROLE_USER';
        }

        return array_unique($roles);
    }

    /**
     * {@inheritDoc}
     */
    public function getPassword(): string
    {
        return $this->passwordHash;
    }

    /**
     * {@inheritDoc}
     */
    public function eraseCredentials(): void
    {
        // No sensitive temporary data to erase in this implementation
        // Plain password is never stored in this class
    }

    /**
     * Get the internal User ID.
     */
    public function getUserId(): string
    {
        return $this->userId;
    }
}
