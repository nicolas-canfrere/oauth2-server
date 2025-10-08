<?php

declare(strict_types=1);

namespace App\Domain\Audit\DTO;

use App\Domain\Audit\Enum\AuditEventTypeEnum;

/**
 * Data Transfer Object for audit events.
 *
 * Provides type-safe structure for audit log data before persistence.
 * All audit events must use this DTO to ensure consistent data structure.
 */
readonly class AuditEventDTO
{
    /**
     * @param array<string, mixed> $context
     */
    public function __construct(
        public AuditEventTypeEnum $eventType,
        public string $level,
        public string $message,
        public array $context = [],
        public ?string $userId = null,
        public ?string $clientId = null,
        public ?string $ipAddress = null,
        public ?string $userAgent = null,
    ) {
    }

    /**
     * Creates an audit event for successful login.
     *
     * @param array<string, mixed> $additionalContext
     */
    public static function loginSuccess(
        string $userId,
        string $ipAddress,
        string $userAgent,
        array $additionalContext = [],
    ): self {
        return new self(
            eventType: AuditEventTypeEnum::LOGIN_SUCCESS,
            level: 'info',
            message: 'User logged in successfully',
            context: $additionalContext,
            userId: $userId,
            ipAddress: $ipAddress,
            userAgent: $userAgent
        );
    }

    /**
     * Creates an audit event for failed login.
     *
     * @param array<string, mixed> $additionalContext
     */
    public static function loginFailure(
        string $ipAddress,
        string $userAgent,
        string $reason,
        array $additionalContext = [],
    ): self {
        return new self(
            eventType: AuditEventTypeEnum::LOGIN_FAILURE,
            level: 'warning',
            message: sprintf('Login failed: %s', $reason),
            context: array_merge(['reason' => $reason], $additionalContext),
            ipAddress: $ipAddress,
            userAgent: $userAgent
        );
    }

    /**
     * Creates an audit event for access token issuance.
     *
     * @param array<int, string> $scopes
     * @param array<string, mixed> $additionalContext
     */
    public static function accessTokenIssued(
        string $userId,
        string $clientId,
        string $jti,
        array $scopes,
        string $ipAddress,
        array $additionalContext = [],
    ): self {
        return new self(
            eventType: AuditEventTypeEnum::ACCESS_TOKEN_ISSUED,
            level: 'info',
            message: 'Access token issued',
            context: array_merge([
                'jti' => $jti,
                'scopes' => $scopes,
            ], $additionalContext),
            userId: $userId,
            clientId: $clientId,
            ipAddress: $ipAddress
        );
    }

    /**
     * Creates an audit event for refresh token issuance.
     *
     * @param array<int, string> $scopes
     * @param array<string, mixed> $additionalContext
     */
    public static function refreshTokenIssued(
        string $userId,
        string $clientId,
        string $tokenIdentifier,
        array $scopes,
        string $ipAddress,
        array $additionalContext = [],
    ): self {
        return new self(
            eventType: AuditEventTypeEnum::REFRESH_TOKEN_ISSUED,
            level: 'info',
            message: 'Refresh token issued',
            context: array_merge([
                'token_identifier' => $tokenIdentifier,
                'scopes' => $scopes,
            ], $additionalContext),
            userId: $userId,
            clientId: $clientId,
            ipAddress: $ipAddress
        );
    }

    /**
     * Creates an audit event for token revocation.
     *
     * @param array<string, mixed> $additionalContext
     */
    public static function tokenRevoked(
        AuditEventTypeEnum $tokenType,
        string $tokenIdentifier,
        string $reason,
        ?string $userId = null,
        ?string $clientId = null,
        ?string $ipAddress = null,
        array $additionalContext = [],
    ): self {
        return new self(
            eventType: $tokenType,
            level: 'notice',
            message: sprintf('Token revoked: %s', $reason),
            context: array_merge([
                'token_identifier' => $tokenIdentifier,
                'reason' => $reason,
            ], $additionalContext),
            userId: $userId,
            clientId: $clientId,
            ipAddress: $ipAddress
        );
    }

    /**
     * Creates an audit event for rate limit exceeded.
     *
     * @param array<string, mixed> $additionalContext
     */
    public static function rateLimitExceeded(
        string $limiterName,
        string $ipAddress,
        ?string $userId = null,
        array $additionalContext = [],
    ): self {
        return new self(
            eventType: AuditEventTypeEnum::RATE_LIMIT_EXCEEDED,
            level: 'warning',
            message: sprintf('Rate limit exceeded: %s', $limiterName),
            context: array_merge(['limiter' => $limiterName], $additionalContext),
            userId: $userId,
            ipAddress: $ipAddress
        );
    }

    /**
     * Creates an audit event for invalid client credentials.
     *
     * @param array<string, mixed> $additionalContext
     */
    public static function invalidClientCredentials(
        string $clientId,
        string $ipAddress,
        string $reason,
        array $additionalContext = [],
    ): self {
        return new self(
            eventType: AuditEventTypeEnum::INVALID_CLIENT_CREDENTIALS,
            level: 'warning',
            message: sprintf('Invalid client credentials: %s', $reason),
            context: array_merge(['reason' => $reason], $additionalContext),
            clientId: $clientId,
            ipAddress: $ipAddress
        );
    }

    /**
     * Creates an audit event for successful OAuth2 client authentication.
     *
     * @param array<string, mixed> $additionalContext
     */
    public static function clientAuthenticated(
        string $clientId,
        ?string $ipAddress,
        ?string $userAgent,
        array $additionalContext = [],
    ): self {
        return new self(
            eventType: AuditEventTypeEnum::CLIENT_AUTHENTICATED,
            level: 'info',
            message: sprintf('OAuth2 client "%s" authenticated successfully', $clientId),
            context: $additionalContext,
            clientId: $clientId,
            ipAddress: $ipAddress,
            userAgent: $userAgent
        );
    }

    /**
     * Creates an audit event for failed OAuth2 client authentication.
     *
     * @param array<string, mixed> $additionalContext
     */
    public static function clientAuthenticationFailed(
        ?string $clientId,
        string $ipAddress,
        string $userAgent,
        string $reason,
        array $additionalContext = [],
    ): self {
        return new self(
            eventType: AuditEventTypeEnum::CLIENT_AUTHENTICATION_FAILED,
            level: 'warning',
            message: sprintf('OAuth2 client authentication failed: %s', $reason),
            context: array_merge(['reason' => $reason], $additionalContext),
            clientId: $clientId,
            ipAddress: $ipAddress,
            userAgent: $userAgent
        );
    }

    /**
     * Creates an audit event for OAuth client creation.
     *
     * @param array<string, mixed> $additionalContext
     */
    public static function clientCreated(
        string $clientId,
        string $clientName,
        ?string $userId = null,
        ?string $ipAddress = null,
        ?string $userAgent = null,
        array $additionalContext = [],
    ): self {
        return new self(
            eventType: AuditEventTypeEnum::CLIENT_CREATED,
            level: 'info',
            message: sprintf('OAuth2 client "%s" created', $clientName),
            context: array_merge([
                'client_name' => $clientName,
            ], $additionalContext),
            userId: $userId,
            clientId: $clientId,
            ipAddress: $ipAddress,
            userAgent: $userAgent
        );
    }

    /**
     * Converts DTO to array for JSON serialization.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'event_type' => $this->eventType->value,
            'level' => $this->level,
            'message' => $this->message,
            'context' => $this->context,
            'user_id' => $this->userId,
            'client_id' => $this->clientId,
            'ip_address' => $this->ipAddress,
            'user_agent' => $this->userAgent,
            'timestamp' => date('c'), // ISO 8601 format
        ];
    }
}
