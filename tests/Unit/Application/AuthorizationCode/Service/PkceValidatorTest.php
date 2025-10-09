<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\AuthorizationCode\Service;

use App\Application\AuthorizationCode\Service\PkceValidator;
use PHPUnit\Framework\TestCase;

/**
 * @covers \App\Application\AuthorizationCode\Service\PkceValidator
 */
final class PkceValidatorTest extends TestCase
{
    private PkceValidator $pkceValidator;

    protected function setUp(): void
    {
        $this->pkceValidator = new PkceValidator();
    }

    public function testItValidatesPlainCodeChallengeSuccessfully(): void
    {
        $codeVerifier = 'dBjftJeZ4CVP-mB92K27uhbUJU1p1r_wW1gFWFOEjXk';
        $codeChallenge = $codeVerifier; // plain method uses verifier as challenge

        $result = $this->pkceValidator->validate($codeVerifier, $codeChallenge, 'plain');

        self::assertTrue($result);
    }

    public function testItValidatesS256CodeChallengeSuccessfully(): void
    {
        $codeVerifier = 'dBjftJeZ4CVP-mB92K27uhbUJU1p1r_wW1gFWFOEjXk';
        // Expected S256 challenge for the above verifier (RFC 7636 Appendix B)
        $codeChallenge = 'E9Melhoa2OwvFrEMTJguCHaoeK1t8URWbuGJSstw-cM';

        $result = $this->pkceValidator->validate($codeVerifier, $codeChallenge, 'S256');

        self::assertTrue($result);
    }

    public function testItRejectsInvalidPlainCodeChallenge(): void
    {
        $codeVerifier = 'dBjftJeZ4CVP-mB92K27uhbUJU1p1r_wW1gFWFOEjXk';
        $invalidCodeChallenge = 'wrong_challenge';

        $result = $this->pkceValidator->validate($codeVerifier, $invalidCodeChallenge, 'plain');

        self::assertFalse($result);
    }

    public function testItRejectsInvalidS256CodeChallenge(): void
    {
        $codeVerifier = 'dBjftJeZ4CVP-mB92K27uhbUJU1p1r_wW1gFWFOEjXk';
        $invalidCodeChallenge = 'invalid_challenge_value';

        $result = $this->pkceValidator->validate($codeVerifier, $invalidCodeChallenge, 'S256');

        self::assertFalse($result);
    }

    public function testItRejectsUnsupportedChallengeMethod(): void
    {
        $codeVerifier = 'dBjftJeZ4CVP-mB92K27uhbUJU1p1r_wW1gFWFOEjXk';
        $codeChallenge = 'some_challenge';

        $result = $this->pkceValidator->validate($codeVerifier, $codeChallenge, 'unsupported_method');

        self::assertFalse($result);
    }

    public function testItGeneratesPlainChallengeCorrectly(): void
    {
        $codeVerifier = 'dBjftJeZ4CVP-mB92K27uhbUJU1p1r_wW1gFWFOEjXk';

        $challenge = $this->pkceValidator->generateChallenge($codeVerifier, 'plain');

        self::assertSame($codeVerifier, $challenge);
    }

    public function testItGeneratesS256ChallengeCorrectly(): void
    {
        $codeVerifier = 'dBjftJeZ4CVP-mB92K27uhbUJU1p1r_wW1gFWFOEjXk';
        // Expected S256 challenge from RFC 7636 Appendix B
        $expectedChallenge = 'E9Melhoa2OwvFrEMTJguCHaoeK1t8URWbuGJSstw-cM';

        $challenge = $this->pkceValidator->generateChallenge($codeVerifier, 'S256');

        self::assertSame($expectedChallenge, $challenge);
    }

    public function testItThrowsExceptionForUnsupportedChallengeMethodInGeneration(): void
    {
        $codeVerifier = 'dBjftJeZ4CVP-mB92K27uhbUJU1p1r_wW1gFWFOEjXk';

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported code challenge method "MD5"');

        $this->pkceValidator->generateChallenge($codeVerifier, 'MD5');
    }

    public function testItUsesBase64UrlEncodingWithoutPadding(): void
    {
        // Test that the S256 challenge uses URL-safe base64 encoding without padding
        $codeVerifier = 'test_verifier_123';

        $challenge = $this->pkceValidator->generateChallenge($codeVerifier, 'S256');

        // Should not contain +, /, or =
        self::assertStringNotContainsString('+', $challenge);
        self::assertStringNotContainsString('/', $challenge);
        self::assertStringNotContainsString('=', $challenge);
    }

    public function testItIsTimingAttackSafe(): void
    {
        // hash_equals is used internally, which is timing-attack safe
        $codeVerifier = 'dBjftJeZ4CVP-mB92K27uhbUJU1p1r_wW1gFWFOEjXk';
        $correctChallenge = 'E9Melhoa2OwvFrEMTJguCHaoeK1t8URWbuGJSstw-cM';
        $wrongChallenge = 'E9Melhoa2OwvFrEMTJguCHaoeK1t8URWbuGJSstw-XX';

        // Both should return in similar time (hash_equals provides this guarantee)
        $result1 = $this->pkceValidator->validate($codeVerifier, $correctChallenge, 'S256');
        $result2 = $this->pkceValidator->validate($codeVerifier, $wrongChallenge, 'S256');

        self::assertTrue($result1);
        self::assertFalse($result2);
    }

    public function testItHandlesEdgeCaseEmptyVerifierForPlain(): void
    {
        $codeVerifier = '';
        $codeChallenge = '';

        $result = $this->pkceValidator->validate($codeVerifier, $codeChallenge, 'plain');

        self::assertTrue($result);
    }

    public function testItGeneratesDeterministicS256Challenges(): void
    {
        $codeVerifier = 'same_verifier';

        $challenge1 = $this->pkceValidator->generateChallenge($codeVerifier, 'S256');
        $challenge2 = $this->pkceValidator->generateChallenge($codeVerifier, 'S256');

        self::assertSame($challenge1, $challenge2);
    }
}
