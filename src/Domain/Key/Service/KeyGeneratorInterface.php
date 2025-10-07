<?php

declare(strict_types=1);

namespace App\Domain\Key\Service;

use App\Domain\Key\Enum\KeyAlgorithmEnum;

interface KeyGeneratorInterface
{
    public function generateKeyPair(KeyAlgorithmEnum $keyAlgorithmEnum): KeyPairDTO;
}
