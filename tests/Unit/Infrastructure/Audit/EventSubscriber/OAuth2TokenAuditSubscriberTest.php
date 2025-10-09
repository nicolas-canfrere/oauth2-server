<?php

declare(strict_types=1);

namespace App\Tests\Unit\Infrastructure\Audit\EventSubscriber;

use App\Domain\AccessToken\Event\AccessTokenIssuedEvent;
use App\Domain\Audit\DTO\AuditEventDTO;
use App\Domain\Audit\Enum\AuditEventTypeEnum;
use App\Domain\Audit\Service\AuditLoggerInterface;
use App\Domain\RefreshToken\Event\RefreshTokenIssuedEvent;
use App\Infrastructure\Audit\EventSubscriber\OAuth2TokenAuditSubscriber;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * @covers \App\Infrastructure\Audit\EventSubscriber\OAuth2TokenAuditSubscriber
 */
final class OAuth2TokenAuditSubscriberTest extends TestCase
{
    private AuditLoggerInterface $auditLogger;
    private RequestStack $requestStack;
    private OAuth2TokenAuditSubscriber $subscriber;

    protected function setUp(): void
    {
        $this->auditLogger = $this->createMock(AuditLoggerInterface::class);
        $this->requestStack = $this->createMock(RequestStack::class);

        // Par défaut, pas de requête HTTP (contexte CLI)
        $this->requestStack->method('getCurrentRequest')->willReturn(null);

        $this->subscriber = new OAuth2TokenAuditSubscriber($this->auditLogger, $this->requestStack);
    }

    public function testGetSubscribedEvents(): void
    {
        $events = OAuth2TokenAuditSubscriber::getSubscribedEvents();

        self::assertArrayHasKey(AccessTokenIssuedEvent::class, $events);
        self::assertArrayHasKey(RefreshTokenIssuedEvent::class, $events);
        self::assertSame('onAccessTokenIssued', $events[AccessTokenIssuedEvent::class]);
        self::assertSame('onRefreshTokenIssued', $events[RefreshTokenIssuedEvent::class]);
    }

    public function testOnAccessTokenIssuedLogsEvent(): void
    {
        $event = new AccessTokenIssuedEvent(
            userId: 'user-123',
            clientId: 'client-abc',
            grantType: 'authorization_code',
            scopes: ['read', 'write'],
            jti: 'jti-xyz'
        );

        $this->auditLogger->expects(self::once())
            ->method('log')
            ->with(self::callback(function (AuditEventDTO $dto) {
                return AuditEventTypeEnum::ACCESS_TOKEN_ISSUED === $dto->eventType
                    && 'info' === $dto->level
                    && 'Access token issued' === $dto->message
                    && 'user-123' === $dto->userId
                    && 'client-abc' === $dto->clientId
                    && 'unknown' === $dto->ipAddress
                    && 'jti-xyz' === $dto->context['jti']
                    && $dto->context['scopes'] === ['read', 'write']
                    && 'authorization_code' === $dto->context['grant_type'];
            }));

        $this->subscriber->onAccessTokenIssued($event);
    }

    public function testOnRefreshTokenIssuedLogsEvent(): void
    {
        $event = new RefreshTokenIssuedEvent(
            userId: 'user-123',
            clientId: 'client-abc',
            tokenId: 'token-id-456',
            scopes: ['read', 'write']
        );

        $this->auditLogger->expects(self::once())
            ->method('log')
            ->with(self::callback(function (AuditEventDTO $dto) {
                return AuditEventTypeEnum::REFRESH_TOKEN_ISSUED === $dto->eventType
                    && 'info' === $dto->level
                    && 'Refresh token issued' === $dto->message
                    && 'user-123' === $dto->userId
                    && 'client-abc' === $dto->clientId
                    && 'unknown' === $dto->ipAddress
                    && 'token-id-456' === $dto->context['token_identifier']
                    && $dto->context['scopes'] === ['read', 'write'];
            }));

        $this->subscriber->onRefreshTokenIssued($event);
    }
}
