<?php

declare(strict_types=1);

namespace App\Tests\Security;

use App\Model\User;
use App\Repository\UserRepository;
use App\Security\SecurityUser;
use App\Security\UserProvider;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;

/**
 * Integration tests for UserProvider.
 *
 * Tests Symfony Security user loading from database.
 */
final class UserProviderTest extends KernelTestCase
{
    private Connection $connection;
    private UserRepository $userRepository;
    private UserProvider $userProvider;

    protected function setUp(): void
    {
        self::bootKernel();

        $container = static::getContainer();
        $this->connection = $container->get('doctrine.dbal.default_connection');
        $this->userRepository = new UserRepository($this->connection);
        $this->userProvider = new UserProvider($this->userRepository);
    }

    protected function tearDown(): void
    {
        self::ensureKernelShutdown();
    }

    public function testLoadUserByIdentifierFindsUser(): void
    {
        $user = $this->createTestUser(
            id: '123e4567-e89b-12d3-a456-426614174001',
            email: 'test@example.com',
            roles: ['ROLE_USER', 'ROLE_ADMIN'],
        );
        $this->userRepository->save($user);

        $securityUser = $this->userProvider->loadUserByIdentifier('test@example.com');

        $this->assertSame('test@example.com', $securityUser->getUserIdentifier());
        $this->assertSame('123e4567-e89b-12d3-a456-426614174001', $securityUser->getUserId());
        $this->assertContains('ROLE_USER', $securityUser->getRoles());
        $this->assertContains('ROLE_ADMIN', $securityUser->getRoles());
    }

    public function testLoadUserByIdentifierThrowsExceptionWhenUserNotFound(): void
    {
        $this->expectException(UserNotFoundException::class);
        $this->expectExceptionMessage('User "nonexistent@example.com" not found.');

        $this->userProvider->loadUserByIdentifier('nonexistent@example.com');
    }

    public function testRefreshUserReloadsUserFromDatabase(): void
    {
        $user = $this->createTestUser(
            id: '223e4567-e89b-12d3-a456-426614174002',
            email: 'refresh@example.com',
            roles: ['ROLE_USER'],
        );
        $this->userRepository->save($user);

        $originalSecurityUser = $this->userProvider->loadUserByIdentifier('refresh@example.com');

        // Update user in database
        $updatedUser = new User(
            id: '223e4567-e89b-12d3-a456-426614174002',
            email: 'refresh@example.com',
            passwordHash: $user->passwordHash,
            is2faEnabled: $user->is2faEnabled,
            totpSecret: $user->totpSecret,
            roles: ['ROLE_USER', 'ROLE_ADMIN'],
            createdAt: $user->createdAt,
        );
        $this->userRepository->save($updatedUser);

        // Refresh should reload from database
        $refreshedSecurityUser = $this->userProvider->refreshUser($originalSecurityUser);

        $this->assertSame('refresh@example.com', $refreshedSecurityUser->getUserIdentifier());
        $this->assertContains('ROLE_ADMIN', $refreshedSecurityUser->getRoles());
    }

    public function testRefreshUserThrowsExceptionForInvalidUserType(): void
    {
        $invalidUser = $this->createMock(\Symfony\Component\Security\Core\User\UserInterface::class);

        $this->expectException(\InvalidArgumentException::class);

        $this->userProvider->refreshUser($invalidUser);
    }

    public function testSupportsClassReturnsTrueForSecurityUser(): void
    {
        $this->assertTrue($this->userProvider->supportsClass(SecurityUser::class));
    }

    public function testSupportsClassReturnsFalseForOtherClasses(): void
    {
        $this->assertFalse($this->userProvider->supportsClass(\stdClass::class));
        $this->assertFalse($this->userProvider->supportsClass(User::class));
    }

    public function testLoadUserByIdentifierHandlesPasswordHash(): void
    {
        $passwordHash = password_hash('SecurePassword123!', PASSWORD_BCRYPT) ?: '';

        $user = $this->createTestUser(
            id: '323e4567-e89b-12d3-a456-426614174003',
            email: 'password@example.com',
            passwordHash: $passwordHash,
        );
        $this->userRepository->save($user);

        $securityUser = $this->userProvider->loadUserByIdentifier('password@example.com');

        $this->assertSame($passwordHash, $securityUser->getPassword());
        $this->assertTrue(password_verify('SecurePassword123!', $securityUser->getPassword()));
    }

    public function testLoadUserByIdentifierWith2faEnabled(): void
    {
        $user = $this->createTestUser(
            id: '423e4567-e89b-12d3-a456-426614174004',
            email: '2fa@example.com',
            is2faEnabled: true,
            totpSecret: 'JBSWY3DPEHPK3PXP',
        );
        $this->userRepository->save($user);

        $securityUser = $this->userProvider->loadUserByIdentifier('2fa@example.com');

        $this->assertSame('2fa@example.com', $securityUser->getUserIdentifier());
    }

    public function testLoadUserByIdentifierHandlesMultipleRoles(): void
    {
        $user = $this->createTestUser(
            id: '523e4567-e89b-12d3-a456-426614174005',
            email: 'multi-role@example.com',
            roles: ['ROLE_USER', 'ROLE_ADMIN', 'ROLE_OAUTH_CLIENT'],
        );
        $this->userRepository->save($user);

        $securityUser = $this->userProvider->loadUserByIdentifier('multi-role@example.com');

        $roles = $securityUser->getRoles();
        $this->assertCount(3, $roles);
        $this->assertContains('ROLE_USER', $roles);
        $this->assertContains('ROLE_ADMIN', $roles);
        $this->assertContains('ROLE_OAUTH_CLIENT', $roles);
    }

    public function testRefreshUserThrowsExceptionWhenUserNoLongerExists(): void
    {
        $user = $this->createTestUser(
            id: '623e4567-e89b-12d3-a456-426614174006',
            email: 'deleted@example.com',
        );
        $this->userRepository->save($user);

        $securityUser = $this->userProvider->loadUserByIdentifier('deleted@example.com');

        // Delete user from database
        $this->userRepository->delete('623e4567-e89b-12d3-a456-426614174006');

        $this->expectException(UserNotFoundException::class);
        $this->expectExceptionMessage('User "deleted@example.com" not found.');

        $this->userProvider->refreshUser($securityUser);
    }

    /**
     * @param array<string> $roles
     */
    private function createTestUser(
        string $id = '00000000-0000-0000-0000-000000000001',
        string $email = 'test@example.com',
        string $passwordHash = '',
        bool $is2faEnabled = false,
        ?string $totpSecret = null,
        array $roles = ['ROLE_USER'],
    ): User {
        if ('' === $passwordHash) {
            $passwordHash = password_hash('DefaultPassword123!', PASSWORD_BCRYPT) ?: '';
        }

        return new User(
            id: $id,
            email: $email,
            passwordHash: $passwordHash,
            is2faEnabled: $is2faEnabled,
            totpSecret: $totpSecret,
            roles: $roles,
            createdAt: new \DateTimeImmutable(),
        );
    }
}
