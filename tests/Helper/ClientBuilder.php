<?php

declare(strict_types=1);

namespace App\Tests\Helper;

use App\Domain\OAuthClient\Model\OAuthClient;
use Symfony\Component\Uid\Uuid;

final class ClientBuilder
{
    private string $id;
    private string $clientId;
    private ?string $clientSecretHash = null;
    private string $name;
    private string $redirectUri = 'https://example.com/callback';
    /** @var list<string> */
    private array $grantTypes = ['authorization_code'];
    /** @var list<string> */
    private array $scopes = ['read', 'write'];
    private bool $isConfidential = true;
    private bool $pkceRequired = true;
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->id = Uuid::v4()->toRfc4122();
        $this->clientId = 'client-' . bin2hex(random_bytes(8));
        $this->name = 'Test Client';
        $this->createdAt = new \DateTimeImmutable();
    }

    public static function aClient(): self
    {
        return new self();
    }

    public function withId(string $id): self
    {
        $this->id = $id;

        return $this;
    }

    public function withClientId(string $clientId): self
    {
        $this->clientId = $clientId;

        return $this;
    }

    public function withClientSecretHash(?string $clientSecretHash): self
    {
        $this->clientSecretHash = $clientSecretHash;

        return $this;
    }

    public function confidential(): self
    {
        $this->isConfidential = true;
        $this->clientSecretHash = password_hash('secret', PASSWORD_BCRYPT);

        return $this;
    }

    public function public(): self
    {
        $this->isConfidential = false;
        $this->clientSecretHash = null;

        return $this;
    }

    /** @param list<string> $scopes */
    public function withScopes(array $scopes): self
    {
        $this->scopes = $scopes;

        return $this;
    }

    public function build(): OAuthClient
    {
        return new OAuthClient(
            $this->id,
            $this->clientId,
            $this->clientSecretHash,
            $this->name,
            $this->redirectUri,
            $this->grantTypes,
            $this->scopes,
            $this->isConfidential,
            $this->pkceRequired,
            $this->createdAt
        );
    }
}
