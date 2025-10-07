<?php

declare(strict_types=1);

namespace App\Domain\Key\Service;

use App\Domain\Key\Enum\KeyAlgorithmEnum;

final readonly class KeyGenerator implements KeyGeneratorInterface
{
    /**
     * @param iterable<KeyGeneratorHandlerInterface> $handlers
     */
    public function __construct(
        private iterable $handlers,
    ) {
    }

    public function generateKeyPair(KeyAlgorithmEnum $keyAlgorithmEnum): KeyPairDTO
    {
        foreach ($this->handlers as $handler) {
            if ($handler->supports($keyAlgorithmEnum)) {
                return $handler->generateKeyPair();
            }
        }

        throw new \RuntimeException('Unable to generate key pair for key algorithm ' . $keyAlgorithmEnum->value);
    }
}
