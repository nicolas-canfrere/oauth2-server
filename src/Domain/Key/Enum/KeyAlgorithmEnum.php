<?php

declare(strict_types=1);

namespace App\Domain\Key\Enum;

enum KeyAlgorithmEnum: string
{
    case RSA = 'rsa';
    case ECDSA = 'ecdsa';
}
