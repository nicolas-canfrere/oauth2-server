<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Domain\OAuthClient\Exception\InvalidClientSecretException;
use App\Domain\OAuthClient\Service\ClientSecretGenerator;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for ClientSecretGenerator.
 *
 * Validates cryptographic secret generation and security validation logic.
 */
final class ClientSecretGeneratorTest extends TestCase
{
    private ClientSecretGenerator $generator;

    protected function setUp(): void
    {
        $this->generator = new ClientSecretGenerator();
    }

    public function testGenerateCreatesSecretWithDefaultLength(): void
    {
        $secret = $this->generator->generate();

        // Base64-encoded 32 bytes ≈ 43 characters (URL-safe, no padding)
        $this->assertGreaterThanOrEqual(32, mb_strlen($secret));
    }

    public function testGenerateCreatesSecretWithCustomLength(): void
    {
        $secret = $this->generator->generate(64);

        // Base64-encoded 64 bytes ≈ 85+ characters
        $this->assertGreaterThanOrEqual(64, mb_strlen($secret));
    }

    public function testGenerateCreatesUniqueSecrets(): void
    {
        $secret1 = $this->generator->generate();
        $secret2 = $this->generator->generate();

        $this->assertNotSame($secret1, $secret2);
    }

    public function testGenerateThrowsExceptionForShortLength(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Secret length must be at least 32 bytes');

        $this->generator->generate(16);
    }

    public function testValidateAcceptsStrongSecret(): void
    {
        $strongSecret = $this->generator->generate();

        // Should not throw - expectNotToPerformAssertions documents no exception expected
        $this->expectNotToPerformAssertions();
        $this->generator->validate($strongSecret);
    }

    public function testValidateRejectsTooShortSecret(): void
    {
        $this->expectException(InvalidClientSecretException::class);
        $this->expectExceptionMessage('must be at least 32 characters long');

        $this->generator->validate('tooshort');
    }

    public function testValidateRejectsRepetitivePattern(): void
    {
        $this->expectException(InvalidClientSecretException::class);
        $this->expectExceptionMessage('contains weak patterns');

        $this->generator->validate('aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa'); // 36 chars, all 'a'
    }

    public function testValidateRejectsOnlyLowercase(): void
    {
        $this->expectException(InvalidClientSecretException::class);
        $this->expectExceptionMessage('contains weak patterns');

        $this->generator->validate('abcdefghijklmnopqrstuvwxyzabcdefgh');
    }

    public function testValidateRejectsOnlyNumbers(): void
    {
        $this->expectException(InvalidClientSecretException::class);
        $this->expectExceptionMessage('contains weak patterns');

        $this->generator->validate('12345678901234567890123456789012');
    }

    public function testValidateRejectsCommonWeakPrefixes(): void
    {
        $this->expectException(InvalidClientSecretException::class);
        $this->expectExceptionMessage('contains weak patterns');

        $this->generator->validate('password123456789012345678901234');
    }

    public function testValidateRejectsKeyboardPatterns(): void
    {
        $this->expectException(InvalidClientSecretException::class);
        $this->expectExceptionMessage('contains weak patterns');

        $this->generator->validate('qwerty1234567890123456789012345678');
    }

    public function testValidateRejectsLowEntropy(): void
    {
        $this->expectException(InvalidClientSecretException::class);
        $this->expectExceptionMessage('entropy too low');

        // Low entropy: only 4 unique characters (a,b,1,2) with mixed case to bypass patterns
        // Entropy ≈ 2 bits/char (below 3.5 threshold)
        $this->generator->validate('aAbB12aAbB12aAbB12aAbB12aAbB12aAbB');
    }

    public function testCalculateEntropyReturnsZeroForEmptyString(): void
    {
        $entropy = $this->generator->calculateEntropy('');

        $this->assertSame(0.0, $entropy);
    }

    public function testCalculateEntropyReturnsSameValueForSameInput(): void
    {
        $entropy1 = $this->generator->calculateEntropy('test123');
        $entropy2 = $this->generator->calculateEntropy('test123');

        $this->assertSame($entropy1, $entropy2);
    }

    public function testCalculateEntropyHigherForRandomStrings(): void
    {
        $lowEntropy = $this->generator->calculateEntropy('aaaaaa');
        $highEntropy = $this->generator->calculateEntropy('aB3!xZ');

        $this->assertLessThan($highEntropy, $lowEntropy);
    }

    public function testCalculateEntropyForGeneratedSecret(): void
    {
        $secret = $this->generator->generate();
        $entropy = $this->generator->calculateEntropy($secret);

        // Generated secrets should have high entropy (≥3.5 bits/char)
        $this->assertGreaterThanOrEqual(3.5, $entropy);
    }

    public function testGeneratedSecretPassesValidation(): void
    {
        $secret = $this->generator->generate(32);

        // Generated secret should always pass validation - no exception expected
        $this->expectNotToPerformAssertions();
        $this->generator->validate($secret);
    }

    public function testValidateAcceptsManuallyCreatedStrongSecret(): void
    {
        // Manually created strong secret (32+ chars, high entropy, mixed characters)
        $strongSecret = 'aB3!xZ9$mN7@qW5#pL8&kR2^jT6*hY4%fV1';

        $this->expectNotToPerformAssertions();
        $this->generator->validate($strongSecret);
    }
}
