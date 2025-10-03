<?php

declare(strict_types=1);

namespace App\Tests\Security;

use App\Security\SecurityUser;
use App\Tests\Helper\UserBuilder;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for SecurityUser.
 *
 * Tests Symfony Security UserInterface implementation.
 */
final class SecurityUserTest extends TestCase
{
    public function testFromUserCreatesSecurityUser(): void
    {
        $user = UserBuilder::aUser()
            ->withId('123e4567-e89b-12d3-a456-426614174001')
            ->withEmail('test@example.com')
            ->withPasswordHash('$2y$10$abcdefghijklmnopqrstuv')
            ->withRoles(['ROLE_USER', 'ROLE_ADMIN'])
            ->build();

        $securityUser = SecurityUser::fromUser($user);

        $this->assertSame('test@example.com', $securityUser->getUserIdentifier());
        $this->assertSame('$2y$10$abcdefghijklmnopqrstuv', $securityUser->getPassword());
        $this->assertSame('123e4567-e89b-12d3-a456-426614174001', $securityUser->getUserId());
        $this->assertContains('ROLE_USER', $securityUser->getRoles());
        $this->assertContains('ROLE_ADMIN', $securityUser->getRoles());
    }

    public function testGetRolesAlwaysIncludesRoleUser(): void
    {
        $user = UserBuilder::aUser()
            ->withId('123e4567-e89b-12d3-a456-426614174001')
            ->withEmail('test@example.com')
            ->withPasswordHash('$2y$10$abcdefghijklmnopqrstuv')
            ->withRoles(['ROLE_ADMIN'])
            ->build();

        $securityUser = SecurityUser::fromUser($user);
        $roles = $securityUser->getRoles();

        $this->assertContains('ROLE_USER', $roles);
        $this->assertContains('ROLE_ADMIN', $roles);
    }

    public function testGetRolesReturnsUniqueValues(): void
    {
        $user = UserBuilder::aUser()
            ->withId('123e4567-e89b-12d3-a456-426614174001')
            ->withEmail('test@example.com')
            ->withPasswordHash('$2y$10$abcdefghijklmnopqrstuv')
            ->withRoles(['ROLE_USER', 'ROLE_ADMIN', 'ROLE_USER'])
            ->build();

        $securityUser = SecurityUser::fromUser($user);
        $roles = $securityUser->getRoles();

        // Should contain ROLE_USER only once
        $this->assertCount(2, $roles);
        $this->assertContains('ROLE_USER', $roles);
        $this->assertContains('ROLE_ADMIN', $roles);
    }

    public function testGetUserIdentifierReturnsEmail(): void
    {
        $user = UserBuilder::aUser()
            ->withId('123e4567-e89b-12d3-a456-426614174001')
            ->withEmail('user@example.com')
            ->withPasswordHash('$2y$10$abcdefghijklmnopqrstuv')
            ->build();

        $securityUser = SecurityUser::fromUser($user);

        $this->assertSame('user@example.com', $securityUser->getUserIdentifier());
    }

    public function testGetPasswordReturnsHashedPassword(): void
    {
        $passwordHash = password_hash('SecurePassword123!', PASSWORD_BCRYPT) ?: '';

        $user = UserBuilder::aUser()
            ->withId('123e4567-e89b-12d3-a456-426614174001')
            ->withEmail('test@example.com')
            ->withPasswordHash($passwordHash)
            ->build();

        $securityUser = SecurityUser::fromUser($user);

        $this->assertSame($passwordHash, $securityUser->getPassword());
        $this->assertTrue(password_verify('SecurePassword123!', $securityUser->getPassword()));
    }

    public function testEraseCredentialsDoesNothing(): void
    {
        $user = UserBuilder::aUser()
            ->withId('123e4567-e89b-12d3-a456-426614174001')
            ->withEmail('test@example.com')
            ->withPasswordHash('$2y$10$abcdefghijklmnopqrstuv')
            ->build();

        $securityUser = SecurityUser::fromUser($user);
        $passwordBefore = $securityUser->getPassword();

        $securityUser->eraseCredentials();

        // Password should remain unchanged (readonly class)
        $this->assertSame($passwordBefore, $securityUser->getPassword());
    }

    public function testFromUserWithDefaultRole(): void
    {
        $user = UserBuilder::aUser()
            ->withId('123e4567-e89b-12d3-a456-426614174001')
            ->withEmail('test@example.com')
            ->withPasswordHash('$2y$10$abcdefghijklmnopqrstuv')
            ->build();

        $securityUser = SecurityUser::fromUser($user);

        $this->assertSame(['ROLE_USER'], $securityUser->getRoles());
    }

    public function testFromUserWithMultipleRoles(): void
    {
        $user = UserBuilder::aUser()
            ->withId('123e4567-e89b-12d3-a456-426614174001')
            ->withEmail('admin@example.com')
            ->withPasswordHash('$2y$10$abcdefghijklmnopqrstuv')
            ->withRoles(['ROLE_USER', 'ROLE_ADMIN', 'ROLE_OAUTH_CLIENT'])
            ->build();

        $securityUser = SecurityUser::fromUser($user);
        $roles = $securityUser->getRoles();

        $this->assertCount(3, $roles);
        $this->assertContains('ROLE_USER', $roles);
        $this->assertContains('ROLE_ADMIN', $roles);
        $this->assertContains('ROLE_OAUTH_CLIENT', $roles);
    }

    public function testFromUserPreservesValidRoles(): void
    {
        $user = UserBuilder::aUser()
            ->withId('123e4567-e89b-12d3-a456-426614174001')
            ->withEmail('test@example.com')
            ->withPasswordHash('$2y$10$abcdefghijklmnopqrstuv')
            ->withRoles(['ROLE_USER', 'ROLE_ADMIN'])
            ->build();

        $securityUser = SecurityUser::fromUser($user);
        $roles = $securityUser->getRoles();

        // All valid string roles should be preserved
        $this->assertContains('ROLE_USER', $roles);
        $this->assertContains('ROLE_ADMIN', $roles);
        $this->assertCount(2, $roles);
    }
}
