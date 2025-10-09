<?php

declare(strict_types=1);

namespace App\Domain\RefreshToken\Event;

use Symfony\Contracts\EventDispatcher\Event;

/**
 * Event dispatched when a refresh token is issued.
 *
 * This domain event allows the application to react to refresh token issuance
 * without coupling the domain logic to infrastructure concerns like audit logging.
 */
final class RefreshTokenIssuedEvent extends Event
{
    /**
     * @param list<string> $scopes
     */
    public function __construct(
        public readonly string $userId,
        public readonly string $clientId,
        public readonly string $tokenId,
        public readonly array $scopes,
    ) {
    }
}
