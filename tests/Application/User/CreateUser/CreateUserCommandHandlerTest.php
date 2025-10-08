<?php

declare(strict_types=1);

namespace App\Tests\Application\User\CreateUser;

use App\Application\User\CreateUser\CreateUserCommand;
use App\Application\User\CreateUser\CreateUserCommandHandler;
use App\Domain\User\Model\User;
use App\Domain\User\Repository\UserRepositoryInterface;
use App\Domain\User\Service\UserPasswordHasherInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * @covers \App\Application\User\CreateUser\CreateUserCommandHandler
 */
final class CreateUserCommandHandlerTest extends TestCase
{
    private UserRepositoryInterface&MockObject $userRepository;
    private UserPasswordHasherInterface&MockObject $passwordHasher;
    private CreateUserCommandHandler $handler;

    protected function setUp(): void
    {
        $this->userRepository = $this->createMock(UserRepositoryInterface::class);
        $this->passwordHasher = $this->createMock(UserPasswordHasherInterface::class);

        $this->handler = new CreateUserCommandHandler(
            $this->passwordHasher,
            $this->userRepository,
        );
    }

    public function testCreatesUserSuccessfully(): void
    {
        $command = new CreateUserCommand(
            email: 'User@example.com',
            plainPassword: 'SecurePass!234',
            roles: ['role_admin'],
            isTwoFactorEnabled: true,
            totpSecret: 'TOTPSECRET',
        );

        $this->userRepository->expects(self::once())
            ->method('findByEmail')
            ->with('user@example.com')
            ->willReturn(null);

        $this->passwordHasher->expects(self::once())
            ->method('hash')
            ->with('SecurePass!234')
            ->willReturn('hashed-password');

        $this->userRepository->expects(self::once())
            ->method('create')
            ->with(self::callback(function (User $user): bool {
                self::assertSame('user@example.com', $user->email);
                self::assertSame('hashed-password', $user->passwordHash);
                self::assertTrue($user->is2faEnabled);
                self::assertSame('TOTPSECRET', $user->totpSecret);
                self::assertSame(['ROLE_ADMIN'], $user->roles);
                self::assertNotEmpty($user->id);

                return true;
            }));

        $userId = ($this->handler)($command);

        self::assertNotEmpty($userId);
    }

    public function testThrowsWhenEmailAlreadyExists(): void
    {
        $command = new CreateUserCommand(
            email: 'existing@example.com',
            plainPassword: 'password',
        );

        $existingUser = new User(
            id: 'existing-id',
            email: 'existing@example.com',
            passwordHash: 'hash',
            is2faEnabled: false,
            totpSecret: null,
            roles: ['ROLE_USER'],
            createdAt: new \DateTimeImmutable(),
        );

        $this->userRepository->expects(self::once())
            ->method('findByEmail')
            ->with('existing@example.com')
            ->willReturn($existingUser);

        $this->passwordHasher->expects(self::never())
            ->method('hash');

        $this->userRepository->expects(self::never())
            ->method('create');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('User with email "existing@example.com" already exists.');

        ($this->handler)($command);
    }

    public function testThrowsWhenEmailInvalid(): void
    {
        $command = new CreateUserCommand(
            email: 'invalid-email',
            plainPassword: 'password',
        );

        $this->passwordHasher->expects(self::never())
            ->method('hash');

        $this->expectException(\InvalidArgumentException::class);

        ($this->handler)($command);
    }

    public function testThrowsWhenPasswordEmpty(): void
    {
        $command = new CreateUserCommand(
            email: 'user@example.com',
            plainPassword: '',
        );

        $this->passwordHasher->expects(self::never())
            ->method('hash');

        $this->userRepository->expects(self::never())
            ->method('create');

        $this->expectException(\InvalidArgumentException::class);

        ($this->handler)($command);
    }

    public function testDefaultsRolesAndDisablesTotpWhenNotRequested(): void
    {
        $command = new CreateUserCommand(
            email: 'user@example.com',
            plainPassword: 'password',
            roles: [],
            isTwoFactorEnabled: false,
            totpSecret: 'SHOULD_BE_IGNORED',
        );

        $this->userRepository->expects(self::once())
            ->method('findByEmail')
            ->with('user@example.com')
            ->willReturn(null);

        $this->passwordHasher->expects(self::once())
            ->method('hash')
            ->with('password')
            ->willReturn('hashed-password');

        $this->userRepository->expects(self::once())
            ->method('create')
            ->with(self::callback(function (User $user): bool {
                self::assertSame(['ROLE_USER'], $user->roles);
                self::assertFalse($user->is2faEnabled);
                self::assertNull($user->totpSecret);
                self::assertSame('hashed-password', $user->passwordHash);

                return true;
            }));

        ($this->handler)($command);
    }
}
