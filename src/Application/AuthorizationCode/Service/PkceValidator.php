<?php

declare(strict_types=1);

namespace App\Application\AuthorizationCode\Service;

/**
 * PKCE (Proof Key for Code Exchange) validator implementation.
 *
 * Validates code verifiers against code challenges according to RFC 7636.
 * Supports both S256 (SHA-256) and plain challenge methods.
 */
final readonly class PkceValidator implements PkceValidatorInterface
{
    private const METHOD_PLAIN = 'plain';
    private const METHOD_S256 = 'S256';

    /**
     * {@inheritDoc}
     */
    public function validate(string $codeVerifier, string $codeChallenge, string $codeChallengeMethod): bool
    {
        if (!$this->isValidChallengeMethod($codeChallengeMethod)) {
            return false;
        }

        $computedChallenge = $this->generateChallenge($codeVerifier, $codeChallengeMethod);

        return hash_equals($codeChallenge, $computedChallenge);
    }

    /**
     * {@inheritDoc}
     */
    public function generateChallenge(string $codeVerifier, string $codeChallengeMethod): string
    {
        if (!$this->isValidChallengeMethod($codeChallengeMethod)) {
            throw new \InvalidArgumentException(
                sprintf('Unsupported code challenge method "%s". Supported methods: S256, plain', $codeChallengeMethod)
            );
        }

        return match ($codeChallengeMethod) {
            self::METHOD_S256 => $this->generateS256Challenge($codeVerifier),
            self::METHOD_PLAIN => $codeVerifier,
            default => throw new \InvalidArgumentException(
                sprintf('Unsupported code challenge method "%s"', $codeChallengeMethod)
            ),
        };
    }

    /**
     * Check if the challenge method is valid.
     */
    private function isValidChallengeMethod(string $codeChallengeMethod): bool
    {
        return in_array($codeChallengeMethod, [self::METHOD_S256, self::METHOD_PLAIN], true);
    }

    /**
     * Generate S256 code challenge.
     *
     * The challenge is the Base64-URL encoded SHA-256 hash of the verifier.
     */
    private function generateS256Challenge(string $codeVerifier): string
    {
        $hash = hash('sha256', $codeVerifier, true);

        return $this->base64UrlEncode($hash);
    }

    /**
     * Base64-URL encode a string (RFC 7636 Section 4.2).
     *
     * This is standard Base64 encoding with URL-safe characters:
     * - '+' replaced with '-'
     * - '/' replaced with '_'
     * - Padding '=' removed
     */
    private function base64UrlEncode(string $data): string
    {
        $base64 = base64_encode($data);
        $base64Url = strtr($base64, '+/', '-_');

        return rtrim($base64Url, '=');
    }
}
