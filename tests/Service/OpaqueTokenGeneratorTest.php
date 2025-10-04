<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Service\OpaqueTokenGenerator;
use PHPUnit\Framework\TestCase;

/**
 * @covers \App\Service\OpaqueTokenGenerator
 */
final class OpaqueTokenGeneratorTest extends TestCase
{
    private OpaqueTokenGenerator $generator;

    protected function setUp(): void
    {
        $this->generator = new OpaqueTokenGenerator();
    }

    public function testGenerateReturnsNonEmptyString(): void
    {
        $token = $this->generator->generate();

        self::assertNotEmpty($token);
    }

    public function testGenerateReturnsExpectedLength(): void
    {
        $token = $this->generator->generate();

        // 32 bytes encoded in base64url produces 43 characters (no padding)
        // Base64 encoding: ceil(n / 3) * 4 = ceil(32 / 3) * 4 = 44, minus 1 padding char = 43
        self::assertSame(43, strlen($token));
    }

    public function testGenerateReturnsBase64UrlSafeCharacters(): void
    {
        $token = $this->generator->generate();

        // Base64url uses: A-Z, a-z, 0-9, -, _ (no +, /, or = padding)
        self::assertMatchesRegularExpression('/^[A-Za-z0-9\-_]+$/', $token);
    }

    public function testGenerateDoesNotContainBase64PaddingCharacters(): void
    {
        $token = $this->generator->generate();

        // Base64url tokens should not have padding characters
        self::assertStringNotContainsString('=', $token);
    }

    public function testGenerateDoesNotContainBase64SpecialCharacters(): void
    {
        $token = $this->generator->generate();

        // Base64url replaces '+' with '-' and '/' with '_'
        self::assertStringNotContainsString('+', $token);
        self::assertStringNotContainsString('/', $token);
    }

    public function testGenerateProducesUniqueTokens(): void
    {
        $tokens = [];

        // Generate 1000 tokens to test for collisions (should be extremely rare with 256 bits)
        for ($i = 0; $i < 1000; ++$i) {
            $tokens[] = $this->generator->generate();
        }

        $uniqueTokens = array_unique($tokens);

        // All tokens should be unique
        self::assertCount(1000, $uniqueTokens);
    }

    public function testGenerateProducesDifferentTokensOnConsecutiveCalls(): void
    {
        $token1 = $this->generator->generate();
        $token2 = $this->generator->generate();

        self::assertNotSame($token1, $token2);
    }

    public function testGeneratedTokenCanBeDecodedFromBase64Url(): void
    {
        $token = $this->generator->generate();

        // Convert base64url back to base64
        $base64 = strtr($token, '-_', '+/');
        // Add padding if needed
        $base64 = str_pad($base64, strlen($base64) + (4 - strlen($base64) % 4) % 4, '=');

        $decoded = base64_decode($base64, true);

        // Should decode successfully to 32 bytes
        self::assertNotFalse($decoded);
        self::assertSame(32, strlen($decoded));
    }

    public function testGeneratedTokenHasSufficientEntropy(): void
    {
        $tokens = [];

        // Generate multiple tokens and check their randomness
        for ($i = 0; $i < 100; ++$i) {
            $tokens[] = $this->generator->generate();
        }

        // Calculate character distribution to verify randomness
        $allChars = implode('', $tokens);
        $charCounts = count_chars($allChars, 1);

        // In base64url, we should see a relatively even distribution across:
        // - A-Z (26 chars)
        // - a-z (26 chars)
        // - 0-9 (10 chars)
        // - - and _ (2 chars)
        // Total: 64 possible characters

        // With 100 tokens * 43 chars = 4300 chars total
        // Expected average per character: 4300 / 64 ≈ 67.19
        // We should see at least 30 different characters used (relaxed threshold)
        self::assertGreaterThanOrEqual(30, count($charCounts));
    }

    public function testGenerateIsConsistentWithInterface(): void
    {
        $token = $this->generator->generate();

        // Verify return type matches interface contract (non-empty string)
        self::assertNotEmpty($token);
    }

    /**
     * Integration test: verify generated tokens can be hashed and validated.
     *
     * This test demonstrates the expected workflow:
     * 1. Generate opaque token
     * 2. Hash it with TokenHasher (simulated with hash())
     * 3. Store hashed value in database
     * 4. Validate by hashing incoming token and comparing
     */
    public function testGeneratedTokenCanBeHashedForStorage(): void
    {
        // Generate token
        $plainToken = $this->generator->generate();

        // Simulate hashing with TokenHasher (using SHA-256)
        $hashedToken = hash('sha256', $plainToken);

        // Verify hash is deterministic
        $hashedAgain = hash('sha256', $plainToken);
        self::assertSame($hashedToken, $hashedAgain);

        // Verify different tokens produce different hashes
        $differentToken = $this->generator->generate();
        $differentHash = hash('sha256', $differentToken);
        self::assertNotSame($hashedToken, $differentHash);
    }
}
