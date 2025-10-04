<?php

declare(strict_types=1);

namespace App\OAuth2\DTO;

/**
 * Data Transfer Object representing JWT token payload claims.
 *
 * Contains all standard JWT claims according to RFC 7519
 * and OAuth2-specific claims according to RFC 9068.
 *
 * @see https://datatracker.ietf.org/doc/html/rfc7519 JWT Claims
 * @see https://datatracker.ietf.org/doc/html/rfc9068 OAuth 2.0 Access Token JWT Profile
 */
final readonly class JwtPayloadDTO
{
    /**
     * @param string      $subject         Subject (sub) - typically the user ID
     * @param string      $audience        Audience (aud) - typically the client ID
     * @param string      $scope           Space-separated list of scopes granted
     * @param int         $expiresIn       Token lifetime in seconds
     * @param string|null $clientId        OAuth2 client identifier (optional)
     * @param int|null    $notBefore       Not Before timestamp (nbf) - token not valid before this time (optional)
     * @param array<string, mixed> $additionalClaims Additional custom claims (optional)
     */
    public function __construct(
        public string $subject,
        public string $audience,
        public string $scope,
        public int $expiresIn,
        public ?string $clientId = null,
        public ?int $notBefore = null,
        public array $additionalClaims = [],
    ) {
        $this->validate();
    }

    /**
     * Convert the DTO to an array of JWT claims.
     *
     * Generates standard JWT claims (iss, sub, aud, exp, iat, jti, nbf)
     * and OAuth2-specific claims (scope, client_id).
     *
     * @param string $issuer The issuer claim (iss) - OAuth2 server URL
     *
     * @return array<string, mixed> Array of JWT claims ready for encoding
     */
    public function toClaims(string $issuer): array
    {
        $now = time();
        $jti = \Symfony\Component\Uid\Uuid::v4()->toRfc4122();

        /** @var array<string, mixed> $claims */
        $claims = [
            'iss' => $issuer,                           // Issuer
            'sub' => $this->subject,                    // Subject (user ID)
            'aud' => $this->audience,                   // Audience (client ID)
            'exp' => $now + $this->expiresIn,          // Expiration time
            'iat' => $now,                              // Issued at
            'jti' => $jti,                              // JWT ID (unique identifier)
            'scope' => $this->scope,                    // OAuth2 scopes
        ];

        // Add optional claims
        if (null !== $this->notBefore) {
            $claims['nbf'] = $this->notBefore;
        }

        if (null !== $this->clientId) {
            $claims['client_id'] = $this->clientId;
        }

        // Merge additional custom claims
        return array_merge($claims, $this->additionalClaims);
    }

    /**
     * Validate DTO constraints.
     *
     * @throws \InvalidArgumentException If validation fails
     */
    private function validate(): void
    {
        if (empty($this->subject)) {
            throw new \InvalidArgumentException('JWT subject (sub) cannot be empty');
        }

        if (empty($this->audience)) {
            throw new \InvalidArgumentException('JWT audience (aud) cannot be empty');
        }

        if (empty($this->scope)) {
            throw new \InvalidArgumentException('JWT scope cannot be empty');
        }

        if ($this->expiresIn <= 0) {
            throw new \InvalidArgumentException('JWT expiresIn must be greater than 0');
        }

        if (null !== $this->notBefore && $this->notBefore < 0) {
            throw new \InvalidArgumentException('JWT notBefore (nbf) must be a positive timestamp');
        }
    }
}
