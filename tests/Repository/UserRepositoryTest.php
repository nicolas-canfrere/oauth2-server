<?php

declare(strict_types=1);

namespace App\Tests\Repository;

use App\Infrastructure\Persistance\Doctrine\Repository\UserRepository;
use App\Model\User;
use App\Tests\Helper\UserBuilder;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Unit tests for UserRepository.
 *
 * Tests CRUD operations and authentication-related functionality using User model.
 */
#[Group('user')]
final class UserRepositoryTest extends KernelTestCase
{
    private Connection $connection;
    private UserRepository $repository;

    protected function setUp(): void
    {
        // Boot Symfony kernel for test environment
        self::bootKernel();

        // Get services from container
        $container = static::getContainer();
        $this->connection = $container->get('doctrine.dbal.default_connection');
        $this->repository = new UserRepository($this->connection);
    }

    protected function tearDown(): void
    {
        self::ensureKernelShutdown();
    }

    public function testCreateAndFindUser(): void
    {
        $user = UserBuilder::aUser()
            ->withId('123e4567-e89b-12d3-a456-426614174001')
            ->withEmail('test@example.com')
            ->withPasswordHash(password_hash('SecurePassword123!', PASSWORD_BCRYPT) ?: '')
            ->build();

        $this->repository->create($user);

        $foundUser = $this->repository->find('123e4567-e89b-12d3-a456-426614174001');

        $this->assertNotNull($foundUser);
        $this->assertSame('123e4567-e89b-12d3-a456-426614174001', $foundUser->id);
        $this->assertSame('test@example.com', $foundUser->email);
        $this->assertTrue(password_verify('SecurePassword123!', $foundUser->passwordHash));
        $this->assertFalse($foundUser->is2faEnabled);
        $this->assertNull($foundUser->totpSecret);
    }

    public function testFindByEmail(): void
    {
        $user = UserBuilder::aUser()
            ->withId('223e4567-e89b-12d3-a456-426614174002')
            ->withEmail('find-by-email@example.com')
            ->build();

        $this->repository->create($user);

        $foundUser = $this->repository->findByEmail('find-by-email@example.com');

        $this->assertNotNull($foundUser);
        $this->assertSame('223e4567-e89b-12d3-a456-426614174002', $foundUser->id);
        $this->assertSame('find-by-email@example.com', $foundUser->email);
    }

    public function testFindByEmailNonExistent(): void
    {
        $result = $this->repository->findByEmail('nonexistent@example.com');

        $this->assertNull($result);
    }

    public function testFindNonExistentUser(): void
    {
        $result = $this->repository->find('00000000-0000-0000-0000-000000000000');

        $this->assertNull($result);
    }

    public function testUpdateUser(): void
    {
        $user = UserBuilder::aUser()
            ->withId('323e4567-e89b-12d3-a456-426614174003')
            ->withEmail('original@example.com')
            ->build();

        $this->repository->create($user);

        // Create updated version
        $updatedUser = new User(
            id: '323e4567-e89b-12d3-a456-426614174003',
            email: 'updated@example.com',
            passwordHash: $user->passwordHash,
            is2faEnabled: true,
            totpSecret: 'JBSWY3DPEHPK3PXP',
            roles: $user->roles,
            createdAt: $user->createdAt,
        );

        $this->repository->update($updatedUser);

        $foundUser = $this->repository->find('323e4567-e89b-12d3-a456-426614174003');

        $this->assertNotNull($foundUser);
        $this->assertSame('updated@example.com', $foundUser->email);
        $this->assertTrue($foundUser->is2faEnabled);
        $this->assertSame('JBSWY3DPEHPK3PXP', $foundUser->totpSecret);
    }

    public function testUpdatePassword(): void
    {
        $user = UserBuilder::aUser()
            ->withId('423e4567-e89b-12d3-a456-426614174004')
            ->withEmail('password-update@example.com')
            ->withPasswordHash(password_hash('OldPassword123!', PASSWORD_BCRYPT) ?: '')
            ->build();

        $this->repository->create($user);

        // Update password
        $newPasswordHash = password_hash('NewPassword456!', PASSWORD_BCRYPT) ?: '';
        $this->repository->updatePassword('423e4567-e89b-12d3-a456-426614174004', $newPasswordHash);

        $foundUser = $this->repository->find('423e4567-e89b-12d3-a456-426614174004');

        $this->assertNotNull($foundUser);
        $this->assertFalse(password_verify('OldPassword123!', $foundUser->passwordHash));
        $this->assertTrue(password_verify('NewPassword456!', $foundUser->passwordHash));
    }

    public function testUpdatePasswordNonExistentUser(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('User with ID "00000000-0000-0000-0000-000000000000" not found');

        $this->repository->updatePassword(
            '00000000-0000-0000-0000-000000000000',
            password_hash('NewPassword', PASSWORD_BCRYPT) ?: ''
        );
    }

    public function testDeleteUser(): void
    {
        $user = UserBuilder::aUser()
            ->withId('523e4567-e89b-12d3-a456-426614174005')
            ->withEmail('to-delete@example.com')
            ->build();

        $this->repository->create($user);

        $this->assertNotNull($this->repository->find('523e4567-e89b-12d3-a456-426614174005'));

        $deleteResult = $this->repository->delete('523e4567-e89b-12d3-a456-426614174005');

        $this->assertTrue($deleteResult);
        $this->assertNull($this->repository->find('523e4567-e89b-12d3-a456-426614174005'));
    }

    public function testDeleteNonExistentUser(): void
    {
        $result = $this->repository->delete('00000000-0000-0000-0000-000000000000');

        $this->assertFalse($result);
    }

    public function testUserWith2faEnabled(): void
    {
        $user = UserBuilder::aUser()
            ->withId('623e4567-e89b-12d3-a456-426614174006')
            ->withEmail('2fa-user@example.com')
            ->with2faEnabled(true)
            ->withTotpSecret('JBSWY3DPEHPK3PXP')
            ->build();

        $this->repository->create($user);

        $foundUser = $this->repository->find('623e4567-e89b-12d3-a456-426614174006');

        $this->assertNotNull($foundUser);
        $this->assertTrue($foundUser->is2faEnabled);
        $this->assertSame('JBSWY3DPEHPK3PXP', $foundUser->totpSecret);
    }

    public function testUserWithoutTotpSecret(): void
    {
        $user = UserBuilder::aUser()
            ->withId('723e4567-e89b-12d3-a456-426614174007')
            ->withEmail('no-totp@example.com')
            ->build();

        $this->repository->create($user);

        $foundUser = $this->repository->find('723e4567-e89b-12d3-a456-426614174007');

        $this->assertNotNull($foundUser);
        $this->assertFalse($foundUser->is2faEnabled);
        $this->assertNull($foundUser->totpSecret);
    }

    public function testDuplicateEmailThrowsException(): void
    {
        $user1 = UserBuilder::aUser()
            ->withId('823e4567-e89b-12d3-a456-426614174008')
            ->withEmail('duplicate@example.com')
            ->build();
        $this->repository->create($user1);

        $user2 = UserBuilder::aUser()
            ->withId('823e4567-e89b-12d3-a456-426614174009')
            ->withEmail('duplicate@example.com')
            ->build();

        // Database has unique constraint on email column
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Failed to create user');

        $this->repository->create($user2);
    }

    public function testPasswordHashingWithBcrypt(): void
    {
        $plainPassword = 'MySecurePassword123!@#';
        $passwordHash = password_hash($plainPassword, PASSWORD_BCRYPT) ?: '';

        $user = UserBuilder::aUser()
            ->withId('923e4567-e89b-12d3-a456-426614174010')
            ->withEmail('bcrypt-test@example.com')
            ->withPasswordHash($passwordHash)
            ->build();

        $this->repository->create($user);

        $foundUser = $this->repository->find('923e4567-e89b-12d3-a456-426614174010');

        $this->assertNotNull($foundUser);
        $this->assertTrue(password_verify($plainPassword, $foundUser->passwordHash));
        $this->assertFalse(password_verify('WrongPassword', $foundUser->passwordHash));
    }

    public function testUpdatedAtTimestampChangesOnUpdate(): void
    {
        $user = UserBuilder::aUser()
            ->withId('a23e4567-e89b-12d3-a456-426614174011')
            ->withEmail('timestamp-test@example.com')
            ->build();

        $this->repository->create($user);

        $foundUser = $this->repository->find('a23e4567-e89b-12d3-a456-426614174011');
        $this->assertNotNull($foundUser);
        $originalUpdatedAt = $foundUser->updatedAt;

        // Wait a moment to ensure timestamp difference
        sleep(1);

        // Update user
        $updatedUser = new User(
            id: 'a23e4567-e89b-12d3-a456-426614174011',
            email: 'timestamp-updated@example.com',
            passwordHash: $foundUser->passwordHash,
            is2faEnabled: $foundUser->is2faEnabled,
            totpSecret: $foundUser->totpSecret,
            roles: $foundUser->roles,
            createdAt: $foundUser->createdAt,
        );

        $this->repository->update($updatedUser);

        $refoundUser = $this->repository->find('a23e4567-e89b-12d3-a456-426614174011');
        $this->assertNotNull($refoundUser);

        // updatedAt should have changed
        $this->assertNotEquals($originalUpdatedAt, $refoundUser->updatedAt);
    }
}
