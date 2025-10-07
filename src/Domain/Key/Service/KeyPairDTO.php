<?php

declare(strict_types=1);

namespace App\Domain\Key\Service;

final readonly class KeyPairDTO
{
    public function __construct(
        public string $publicKey,
        public string $privateKey,
    ) {
    }
}
