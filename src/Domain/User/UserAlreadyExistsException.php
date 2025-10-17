<?php

declare(strict_types=1);

namespace App\Domain\User;

final class UserAlreadyExistsException extends \RuntimeException
{
    public function __construct(string $message = '', ?\Throwable $previous = null)
    {
        parent::__construct($message, 409, $previous);
    }
}
