<?php

declare(strict_types=1);

namespace App\Application\AuthorizationCode\Service;

/**
 * PKCE (Proof Key for Code Exchange) validator interface.
 *
 * Validates code verifiers against code challenges according to RFC 7636.
 * Supports both S256 (SHA-256) and plain challenge methods.
 */
interface PkceValidatorInterface
{
    /**
     * Validate a code verifier against a code challenge.
     *
     * @param string $codeVerifier The code verifier provided by the client
     * @param string $codeChallenge The code challenge stored during authorization
     * @param string $codeChallengeMethod The challenge method (S256 or plain)
     *
     * @return bool True if the verifier is valid, false otherwise
     */
    public function validate(string $codeVerifier, string $codeChallenge, string $codeChallengeMethod): bool;

    /**
     * Generate a code challenge from a code verifier.
     *
     * @param string $codeVerifier The code verifier
     * @param string $codeChallengeMethod The challenge method (S256 or plain)
     *
     * @return string The code challenge
     *
     * @throws \InvalidArgumentException If the challenge method is not supported
     */
    public function generateChallenge(string $codeVerifier, string $codeChallengeMethod): string;
}
