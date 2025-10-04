<?php

declare(strict_types=1);

namespace App\Repository;

/**
 * Exception thrown when repository database operations fail.
 *
 * This exception provides a domain-specific exception type for database errors
 * in repositories, making it easier to distinguish repository failures from
 * other runtime exceptions in the application.
 */
final class RepositoryException extends \RuntimeException
{
}
