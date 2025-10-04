<?php

declare(strict_types=1);

namespace App\Exception;

/**
 * Exception thrown when a client secret fails security validation.
 *
 * Used to enforce OAuth2 security requirements for client credentials.
 */
final class InvalidClientSecretException extends \InvalidArgumentException
{
}
