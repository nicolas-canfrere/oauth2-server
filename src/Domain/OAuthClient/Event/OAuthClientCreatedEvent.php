<?php

declare(strict_types=1);

namespace App\Domain\OAuthClient\Event;

use Symfony\Contracts\EventDispatcher\Event;

/**
 * Event dispatched when an OAuth client is created.
 *
 * This domain event allows the application to react to client creation
 * without coupling the domain logic to infrastructure concerns like audit logging.
 */
final class OAuthClientCreatedEvent extends Event
{
    /**
     * @param list<string> $grantTypes
     * @param list<string> $scopes
     */
    public function __construct(
        public readonly string $clientId,
        public readonly string $clientName,
        public readonly string $redirectUri,
        public readonly array $grantTypes,
        public readonly array $scopes,
        public readonly bool $isConfidential,
        public readonly bool $pkceRequired,
    ) {
    }
}
