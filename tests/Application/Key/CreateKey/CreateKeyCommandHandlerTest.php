<?php

declare(strict_types=1);

namespace App\Tests\Application\Key\CreateKey;

use App\Application\Key\CreateKey\CreateKeyCommand;
use App\Application\Key\CreateKey\CreateKeyCommandHandler;
use App\Domain\Key\Enum\KeyAlgorithmEnum;
use App\Domain\Key\Model\OAuthKey;
use App\Domain\Key\Repository\KeyRepositoryInterface;
use App\Domain\Key\Service\KeyGeneratorInterface;
use App\Domain\Key\Service\KeyPairDTO;
use App\Domain\Key\Service\PrivateKeyEncryptionServiceInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * @covers \App\Application\Key\CreateKey\CreateKeyCommandHandler
 */
final class CreateKeyCommandHandlerTest extends TestCase
{
    private KeyGeneratorInterface&MockObject $keyGenerator;
    private PrivateKeyEncryptionServiceInterface&MockObject $privateKeyEncryption;
    private KeyRepositoryInterface&MockObject $keyRepository;
    private CreateKeyCommandHandler $handler;

    protected function setUp(): void
    {
        $this->keyGenerator = $this->createMock(KeyGeneratorInterface::class);
        $this->privateKeyEncryption = $this->createMock(PrivateKeyEncryptionServiceInterface::class);
        $this->keyRepository = $this->createMock(KeyRepositoryInterface::class);

        $this->handler = new CreateKeyCommandHandler(
            $this->keyGenerator,
            $this->privateKeyEncryption,
            $this->keyRepository
        );
    }

    public function testHandleCreatesRsaKeySuccessfully(): void
    {
        $command = new CreateKeyCommand('rsa');

        $expectedKeyPair = new KeyPairDTO(
            '-----BEGIN PUBLIC KEY-----test-public-key-----END PUBLIC KEY-----',
            '-----BEGIN PRIVATE KEY-----test-private-key-----END PRIVATE KEY-----',
        );

        $this->keyGenerator->expects(self::once())
            ->method('generateKeyPair')
            ->with(KeyAlgorithmEnum::RSA)
            ->willReturn($expectedKeyPair);

        $this->privateKeyEncryption->expects(self::once())
            ->method('encrypt')
            ->with($expectedKeyPair->privateKey)
            ->willReturn('encrypted-private-key');

        $this->keyRepository->expects(self::once())
            ->method('create')
            ->with(self::callback(function (OAuthKey $key) use ($expectedKeyPair): bool {
                return 'rsa' === $key->algorithm
                    && $key->publicKey === $expectedKeyPair->publicKey
                    && 'encrypted-private-key' === $key->privateKeyEncrypted
                    && true === $key->isActive
                    && !empty($key->id)
                    && !empty($key->kid);
            }));

        ($this->handler)($command);
    }

    public function testHandleCreatesEcdsaKeySuccessfully(): void
    {
        $command = new CreateKeyCommand('ecdsa');

        $expectedKeyPair = new KeyPairDTO(
            '-----BEGIN PUBLIC KEY-----test-ecdsa-public-----END PUBLIC KEY-----',
            '-----BEGIN EC PRIVATE KEY-----test-ecdsa-private-----END EC PRIVATE KEY-----',
        );

        $this->keyGenerator->expects(self::once())
            ->method('generateKeyPair')
            ->with(KeyAlgorithmEnum::ECDSA)
            ->willReturn($expectedKeyPair);

        $this->privateKeyEncryption->expects(self::once())
            ->method('encrypt')
            ->with($expectedKeyPair->privateKey)
            ->willReturn('encrypted-ecdsa-private-key');

        $this->keyRepository->expects(self::once())
            ->method('create')
            ->with(self::callback(function (OAuthKey $key) use ($expectedKeyPair): bool {
                return 'ecdsa' === $key->algorithm
                    && $key->publicKey === $expectedKeyPair->publicKey
                    && 'encrypted-ecdsa-private-key' === $key->privateKeyEncrypted
                    && true === $key->isActive;
            }));

        ($this->handler)($command);
    }

    public function testHandleEncryptsPrivateKeyBeforeStorage(): void
    {
        $command = new CreateKeyCommand('rsa');

        $keyPair = new KeyPairDTO(
            'public-key',
            'plain-text-private-key',
        );

        $this->keyGenerator->method('generateKeyPair')
            ->willReturn($keyPair);

        $this->privateKeyEncryption->expects(self::once())
            ->method('encrypt')
            ->with('plain-text-private-key')
            ->willReturn('encrypted-result');

        $this->keyRepository->expects(self::once())
            ->method('create')
            ->with(self::callback(function (OAuthKey $key): bool {
                return 'encrypted-result' === $key->privateKeyEncrypted;
            }));

        ($this->handler)($command);
    }

    public function testHandleCreatesKeyWithValidUuids(): void
    {
        $command = new CreateKeyCommand('rsa');

        $keyPair = new KeyPairDTO(
            'public-key',
            'private-key',
        );

        $this->keyGenerator->method('generateKeyPair')
            ->willReturn($keyPair);

        $this->privateKeyEncryption->method('encrypt')
            ->willReturn('encrypted');

        $this->keyRepository->expects(self::once())
            ->method('create')
            ->with(self::callback(function (OAuthKey $key): bool {
                // Validate UUID v4 format for id
                $uuidPattern = '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i';

                return 1 === preg_match($uuidPattern, $key->id)
                    && 1 === preg_match($uuidPattern, $key->kid)
                    && $key->id !== $key->kid; // id and kid should be different
            }));

        ($this->handler)($command);
    }

    public function testHandleCreatesActiveKey(): void
    {
        $command = new CreateKeyCommand('rsa');

        $keyPair = new KeyPairDTO(
            'public-key',
            'private-key',
        );

        $this->keyGenerator->method('generateKeyPair')
            ->willReturn($keyPair);

        $this->privateKeyEncryption->method('encrypt')
            ->willReturn('encrypted');

        $this->keyRepository->expects(self::once())
            ->method('create')
            ->with(self::callback(function (OAuthKey $key): bool {
                return true === $key->isActive;
            }));

        ($this->handler)($command);
    }

    public function testHandleCreatesKeyWithTimestamps(): void
    {
        $command = new CreateKeyCommand('rsa');

        $keyPair = new KeyPairDTO(
            'public-key',
            'private-key',
        );

        $this->keyGenerator->method('generateKeyPair')
            ->willReturn($keyPair);

        $this->privateKeyEncryption->method('encrypt')
            ->willReturn('encrypted');

        $beforeExecution = new \DateTimeImmutable();

        $this->keyRepository->expects(self::once())
            ->method('create')
            ->with(self::callback(function (OAuthKey $key) use ($beforeExecution): bool {
                $afterExecution = new \DateTimeImmutable();

                return $key->createdAt >= $beforeExecution
                    && $key->createdAt <= $afterExecution
                    && $key->expiresAt >= $beforeExecution
                    && $key->expiresAt <= $afterExecution;
            }));

        ($this->handler)($command);
    }

    public function testHandleStoresPublicKeyUnencrypted(): void
    {
        $command = new CreateKeyCommand('rsa');

        $keyPair = new KeyPairDTO(
            'public-key-plaintext',
            'private-key',
        );

        $this->keyGenerator->method('generateKeyPair')
            ->willReturn($keyPair);

        $this->privateKeyEncryption->method('encrypt')
            ->willReturn('encrypted-private');

        $this->keyRepository->expects(self::once())
            ->method('create')
            ->with(self::callback(function (OAuthKey $key): bool {
                // Public key should be stored as-is, not encrypted
                return 'public-key-plaintext' === $key->publicKey;
            }));

        ($this->handler)($command);
    }

    public function testHandleThrowsExceptionForUnsupportedAlgorithm(): void
    {
        $command = new CreateKeyCommand('unsupported-algorithm');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Unsupported algorithm for creating key.');

        $this->keyGenerator->expects(self::never())
            ->method('generateKeyPair');

        $this->privateKeyEncryption->expects(self::never())
            ->method('encrypt');

        $this->keyRepository->expects(self::never())
            ->method('create');

        ($this->handler)($command);
    }

    public function testHandleThrowsExceptionForEmptyAlgorithm(): void
    {
        $command = new CreateKeyCommand('');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Unsupported algorithm for creating key.');

        ($this->handler)($command);
    }

    public function testHandlePropagatesKeyGeneratorException(): void
    {
        $command = new CreateKeyCommand('rsa');

        $this->keyGenerator->method('generateKeyPair')
            ->willThrowException(new \RuntimeException('Key generation failed'));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Key generation failed');

        ($this->handler)($command);
    }

    public function testHandlePropagatesEncryptionException(): void
    {
        $command = new CreateKeyCommand('rsa');

        $keyPair = new KeyPairDTO(
            'public-key',
            'private-key',
        );

        $this->keyGenerator->method('generateKeyPair')
            ->willReturn($keyPair);

        $this->privateKeyEncryption->method('encrypt')
            ->willThrowException(new \RuntimeException('Encryption failed'));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Encryption failed');

        ($this->handler)($command);
    }

    public function testHandlePropagatesRepositoryException(): void
    {
        $command = new CreateKeyCommand('rsa');

        $keyPair = new KeyPairDTO(
            'public-key',
            'private-key',
        );

        $this->keyGenerator->method('generateKeyPair')
            ->willReturn($keyPair);

        $this->privateKeyEncryption->method('encrypt')
            ->willReturn('encrypted');

        $this->keyRepository->method('create')
            ->willThrowException(new \RuntimeException('Database error'));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Database error');

        ($this->handler)($command);
    }
}
