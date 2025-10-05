<?php

declare(strict_types=1);

namespace App\Application\AccessToken\DTO;

/**
 * Data Transfer Object representing an OAuth2 token response.
 *
 * This DTO encapsulates the token data returned by grant handlers
 * according to RFC 6749 Section 5.1.
 *
 * @see https://datatracker.ietf.org/doc/html/rfc6749#section-5.1
 */
final readonly class TokenResponseDTO
{
    /**
     * @param string                 $accessToken  The access token issued by the authorization server
     * @param string                 $tokenType    The type of the token (typically "Bearer")
     * @param int                    $expiresIn    The lifetime in seconds of the access token
     * @param string|null            $refreshToken The refresh token for obtaining new access tokens (optional)
     * @param string|null            $scope        The scope of the access token (optional, space-separated)
     * @param array<string, mixed>   $additionalData Additional data specific to the grant type (optional)
     */
    public function __construct(
        public string $accessToken,
        public string $tokenType,
        public int $expiresIn,
        public ?string $refreshToken = null,
        public ?string $scope = null,
        public array $additionalData = [],
    ) {
    }

    /**
     * Convert the DTO to an array suitable for JSON encoding.
     *
     * Returns the standard OAuth2 token response format according to RFC 6749.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        /** @var array<string, mixed> $response */
        $response = [
            'access_token' => $this->accessToken,
            'token_type' => $this->tokenType,
            'expires_in' => $this->expiresIn,
        ];

        if (null !== $this->refreshToken) {
            $response['refresh_token'] = $this->refreshToken;
        }

        if (null !== $this->scope) {
            $response['scope'] = $this->scope;
        }

        // Merge additional data (e.g., id_token for OpenID Connect)
        return array_merge($response, $this->additionalData);
    }
}
