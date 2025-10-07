<?php

declare(strict_types=1);

namespace App\Application\Key\CreateKey;

final readonly class CreateKeyCommand
{
    public function __construct(
        public string $algorithm,
    ) {
    }
}
