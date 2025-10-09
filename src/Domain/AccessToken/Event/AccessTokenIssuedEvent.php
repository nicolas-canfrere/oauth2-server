<?php

declare(strict_types=1);

namespace App\Domain\AccessToken\Event;

use Symfony\Contracts\EventDispatcher\Event;

/**
 * Event dispatched when an access token is issued.
 *
 * This domain event allows the application to react to token issuance
 * without coupling the domain logic to infrastructure concerns like audit logging.
 */
final class AccessTokenIssuedEvent extends Event
{
    /**
     * @param list<string> $scopes
     */
    public function __construct(
        public readonly string $userId,
        public readonly string $clientId,
        public readonly string $grantType,
        public readonly array $scopes,
        public readonly string $jti,
    ) {
    }
}
