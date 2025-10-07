<?php

declare(strict_types=1);

namespace App\Application\Key\CreateKey;

use App\Domain\Key\Enum\KeyAlgorithmEnum;
use App\Domain\Key\Model\OAuthKey;
use App\Domain\Key\Repository\KeyRepositoryInterface;
use App\Domain\Key\Service\KeyGeneratorInterface;
use App\Domain\Key\Service\PrivateKeyEncryptionServiceInterface;
use Symfony\Component\Uid\Uuid;

final readonly class CreateKeyCommandHandler
{
    public function __construct(
        private KeyGeneratorInterface $keyGeneratorService,
        private PrivateKeyEncryptionServiceInterface $privateKeyEncryptionService,
        private KeyRepositoryInterface $keyRepository,
    ) {
    }

    public function __invoke(CreateKeyCommand $command): void
    {
        $keyAlgorithm = KeyAlgorithmEnum::tryFrom($command->algorithm);
        if (null === $keyAlgorithm) {
            throw new \RuntimeException('Unsupported algorithm for creating key.');
        }
        $keyPair = $this->keyGeneratorService->generateKeyPair($keyAlgorithm);

        $oauthKey = new OAuthKey(
            Uuid::v4()->toString(),
            Uuid::v4()->toString(),
            $keyAlgorithm->value,
            $keyPair->publicKey,
            $this->privateKeyEncryptionService->encrypt($keyPair->privateKey),
            true,
            new \DateTimeImmutable(),
            new \DateTimeImmutable(),
        );

        $this->keyRepository->create($oauthKey);
    }
}
