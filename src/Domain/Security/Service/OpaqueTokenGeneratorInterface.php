<?php

declare(strict_types=1);

namespace App\Domain\Security\Service;

/**
 * Interface for generating cryptographically secure opaque tokens.
 *
 * Opaque tokens are random, unpredictable strings used for refresh tokens
 * and authorization codes. They contain no embedded information and must
 * be validated by looking them up in the database.
 */
interface OpaqueTokenGeneratorInterface
{
    /**
     * Generates a cryptographically secure opaque token.
     *
     * The token is generated using random_bytes() and encoded in base64url format
     * for URL-safe transmission. The generated token should be hashed before
     * storing in the database.
     *
     * @return string A URL-safe base64-encoded random token (approximately 43 characters for 32 bytes)
     */
    public function generate(): string;
}
