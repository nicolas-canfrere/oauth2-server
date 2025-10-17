<?php

declare(strict_types=1);

namespace App\Tests\Application\OAuthClient\CreateOAuthClient;

use App\Infrastructure\Http\Controller\OAuthClient\CreateOAuthClientDTO;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @covers \App\Infrastructure\Http\Controller\OAuthClient\CreateOAuthClientDTO
 */
final class CreateOAuthClientDTOTest extends KernelTestCase
{
    private ValidatorInterface $validator;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = static::getContainer();
        $this->validator = $container->get(ValidatorInterface::class);
    }

    public function testValidDTOPassesValidation(): void
    {
        $dto = new CreateOAuthClientDTO(
            name: 'My Application',
            redirectUris: ['https://example.com/callback'],
            grantTypes: ['authorization_code'],
            scopes: ['read', 'write'],
            isConfidential: true,
            pkceRequired: true,
        );

        $violations = $this->validator->validate($dto);

        self::assertCount(0, $violations, 'Valid DTO should have no violations');
    }

    public function testNameCannotBeBlank(): void
    {
        $dto = new CreateOAuthClientDTO(
            name: '',
            redirectUris: ['https://example.com/callback'],
        );

        $violations = $this->validator->validate($dto);

        self::assertGreaterThan(0, $violations->count());
        self::assertStringContainsString('name', (string) $violations);
    }

    public function testNameCannotBeTooLong(): void
    {
        $dto = new CreateOAuthClientDTO(
            name: str_repeat('a', 256),
            redirectUris: ['https://example.com/callback'],
        );

        $violations = $this->validator->validate($dto);

        self::assertGreaterThan(0, $violations->count());
        self::assertStringContainsString('name', (string) $violations);
        self::assertStringContainsString('cannot be longer than', (string) $violations);
    }

    public function testRedirectUrisCannotBeEmpty(): void
    {
        $dto = new CreateOAuthClientDTO(
            name: 'Test App',
            redirectUris: [],
        );

        $violations = $this->validator->validate($dto);

        self::assertGreaterThan(0, $violations->count());
        self::assertStringContainsString('redirect', strtolower((string) $violations));
    }

    public function testRedirectUrisMustBeValidUrls(): void
    {
        $dto = new CreateOAuthClientDTO(
            name: 'Test App',
            redirectUris: ['not-a-valid-url', 'also-invalid'],
        );

        $violations = $this->validator->validate($dto);

        self::assertGreaterThan(0, $violations->count());
        self::assertStringContainsString('URL', (string) $violations);
    }

    public function testRedirectUrisCannotContainEmptyStrings(): void
    {
        $dto = new CreateOAuthClientDTO(
            name: 'Test App',
            redirectUris: ['https://example.com/callback', ''],
        );

        $violations = $this->validator->validate($dto);

        self::assertGreaterThan(0, $violations->count());
    }

    public function testGrantTypesCannotBeEmpty(): void
    {
        $dto = new CreateOAuthClientDTO(
            name: 'Test App',
            redirectUris: ['https://example.com/callback'],
            grantTypes: [],
        );

        $violations = $this->validator->validate($dto);

        self::assertGreaterThan(0, $violations->count());
        self::assertStringContainsString('grant', strtolower((string) $violations));
    }

    public function testGrantTypesMustBeStrings(): void
    {
        $dto = new CreateOAuthClientDTO(
            name: 'Test App',
            redirectUris: ['https://example.com/callback'],
            grantTypes: ['authorization_code', 123], // @phpstan-ignore argument.type
        );

        $violations = $this->validator->validate($dto);

        self::assertGreaterThan(0, $violations->count());
    }

    public function testScopesMustBeStrings(): void
    {
        $dto = new CreateOAuthClientDTO(
            name: 'Test App',
            redirectUris: ['https://example.com/callback'],
            scopes: ['read', 456], // @phpstan-ignore argument.type
        );

        $violations = $this->validator->validate($dto);

        self::assertGreaterThan(0, $violations->count());
    }

    public function testIsConfidentialCanBeTrue(): void
    {
        $dto = new CreateOAuthClientDTO(
            name: 'Test App',
            redirectUris: ['https://example.com/callback'],
            isConfidential: true,
        );

        $violations = $this->validator->validate($dto);

        self::assertCount(0, $violations);
        self::assertTrue($dto->isConfidential);
    }

    public function testPkceRequiredCanBeFalse(): void
    {
        $dto = new CreateOAuthClientDTO(
            name: 'Test App',
            redirectUris: ['https://example.com/callback'],
            pkceRequired: false,
        );

        $violations = $this->validator->validate($dto);

        self::assertCount(0, $violations);
        self::assertFalse($dto->pkceRequired);
    }

    public function testClientIdMustBeValidUuidIfProvided(): void
    {
        $dto = new CreateOAuthClientDTO(
            name: 'Test App',
            redirectUris: ['https://example.com/callback'],
            clientId: 'not-a-uuid',
        );

        $violations = $this->validator->validate($dto);

        self::assertGreaterThan(0, $violations->count());
        self::assertStringContainsString('UUID', (string) $violations);
    }

    public function testClientIdCanBeValidUuid(): void
    {
        $dto = new CreateOAuthClientDTO(
            name: 'Test App',
            redirectUris: ['https://example.com/callback'],
            clientId: '550e8400-e29b-41d4-a716-446655440000',
        );

        $violations = $this->validator->validate($dto);

        self::assertCount(0, $violations);
    }

    public function testClientSecretCanBeNull(): void
    {
        $dto = new CreateOAuthClientDTO(
            name: 'Test App',
            redirectUris: ['https://example.com/callback'],
            clientSecret: null,
        );

        $violations = $this->validator->validate($dto);

        self::assertCount(0, $violations);
    }

    public function testMultipleRedirectUris(): void
    {
        $dto = new CreateOAuthClientDTO(
            name: 'Test App',
            redirectUris: [
                'https://example.com/callback',
                'https://app.example.com/oauth/callback',
                'http://localhost:3000/callback',
            ],
        );

        $violations = $this->validator->validate($dto);

        self::assertCount(0, $violations);
    }

    public function testDefaultValues(): void
    {
        $dto = new CreateOAuthClientDTO(
            name: 'Test App',
            redirectUris: ['https://example.com/callback'],
        );

        self::assertSame(['authorization_code'], $dto->grantTypes);
        self::assertSame([], $dto->scopes);
        self::assertFalse($dto->isConfidential);
        self::assertTrue($dto->pkceRequired);
        self::assertNull($dto->clientId);
        self::assertNull($dto->clientSecret);
    }
}
