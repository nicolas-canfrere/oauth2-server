<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Audit event types for OAuth2 server security logging.
 *
 * Each event type represents a significant security or authorization action
 * that must be logged for audit trail, compliance, and security analysis.
 */
enum AuditEventTypeEnum: string
{
    // Authentication events
    case LOGIN_SUCCESS = 'auth.login.success';
    case LOGIN_FAILURE = 'auth.login.failure';

    // Token issuance events
    case ACCESS_TOKEN_ISSUED = 'token.access.issued';
    case REFRESH_TOKEN_ISSUED = 'token.refresh.issued';
    case AUTHORIZATION_CODE_ISSUED = 'token.authorization_code.issued';

    // Token revocation events
    case ACCESS_TOKEN_REVOKED = 'token.access.revoked';
    case REFRESH_TOKEN_REVOKED = 'token.refresh.revoked';
    case AUTHORIZATION_CODE_REVOKED = 'token.authorization_code.revoked';

    // Client management events
    case CLIENT_CREATED = 'client.created';
    case CLIENT_UPDATED = 'client.updated';
    case CLIENT_DELETED = 'client.deleted';

    // Security events
    case RATE_LIMIT_EXCEEDED = 'security.rate_limit.exceeded';
    case INVALID_CLIENT_CREDENTIALS = 'security.client.invalid_credentials';
    case INVALID_GRANT = 'security.grant.invalid';
    case SUSPICIOUS_ACTIVITY = 'security.suspicious_activity';
}
