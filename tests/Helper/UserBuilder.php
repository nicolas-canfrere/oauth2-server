<?php

declare(strict_types=1);

namespace App\Tests\Helper;

use App\Model\User;
use Symfony\Component\Uid\Uuid;

final class UserBuilder
{
    private ?string $id = null;
    private ?string $email = null;
    private ?string $passwordHash = null;
    private bool $is2faEnabled = false;
    private ?string $totpSecret = null;
    /**
     * @var array|string[]
     */
    private array $roles = ['ROLE_USER'];
    private ?\DateTimeImmutable $createdAt = null;
    private ?\DateTimeImmutable $updatedAt = null;

    public static function aUser(): self
    {
        return new self();
    }

    public function withId(string $id): self
    {
        $this->id = $id;

        return $this;
    }

    public function withEmail(string $email): self
    {
        $this->email = $email;

        return $this;
    }

    public function withPasswordHash(string $passwordHash): self
    {
        $this->passwordHash = $passwordHash;

        return $this;
    }

    public function with2faEnabled(bool $is2faEnabled): self
    {
        $this->is2faEnabled = $is2faEnabled;

        return $this;
    }

    public function withTotpSecret(?string $totpSecret): self
    {
        $this->totpSecret = $totpSecret;

        return $this;
    }

    /**
     * @param array|string[] $roles
     */
    public function withRoles(array $roles): self
    {
        $this->roles = $roles;

        return $this;
    }

    public function withCreatedAt(\DateTimeImmutable $createdAt): self
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function withUpdatedAt(\DateTimeImmutable $updatedAt): self
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }

    public function build(): User
    {
        $now = new \DateTimeImmutable();

        return new User(
            id: $this->id ?? Uuid::v4()->toRfc4122(),
            email: $this->email ?? 'test_' . bin2hex(random_bytes(4)) . '@example.com',
            passwordHash: $this->passwordHash ?? password_hash('password123', PASSWORD_BCRYPT),
            is2faEnabled: $this->is2faEnabled,
            totpSecret: $this->totpSecret,
            roles: $this->roles,
            createdAt: $this->createdAt ?? $now,
            updatedAt: $this->updatedAt,
        );
    }
}
