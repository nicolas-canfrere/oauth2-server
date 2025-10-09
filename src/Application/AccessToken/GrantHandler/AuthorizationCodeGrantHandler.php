<?php

declare(strict_types=1);

namespace App\Application\AccessToken\GrantHandler;

use App\Application\AccessToken\DTO\JwtPayloadDTO;
use App\Application\AccessToken\DTO\TokenResponseDTO;
use App\Application\AccessToken\Enum\GrantType;
use App\Application\AccessToken\Exception\InvalidGrantException;
use App\Application\AccessToken\Exception\InvalidRequestException;
use App\Application\AccessToken\Service\JwtTokenGeneratorInterface;
use App\Application\AuthorizationCode\Service\PkceValidatorInterface;
use App\Domain\AccessToken\Event\AccessTokenIssuedEvent;
use App\Domain\Audit\DTO\AuditEventDTO;
use App\Domain\Audit\Enum\AuditEventTypeEnum;
use App\Domain\Audit\Service\AuditLoggerInterface;
use App\Domain\AuthorizationCode\Model\OAuthAuthorizationCode;
use App\Domain\AuthorizationCode\Repository\AuthorizationCodeRepositoryInterface;
use App\Domain\OAuthClient\Model\OAuthClient;
use App\Domain\RefreshToken\Event\RefreshTokenIssuedEvent;
use App\Domain\RefreshToken\Model\OAuthRefreshToken;
use App\Domain\RefreshToken\Repository\RefreshTokenRepositoryInterface;
use App\Domain\Security\Service\OpaqueTokenGeneratorInterface;
use App\Domain\Shared\Factory\IdentityFactoryInterface;
use Psr\EventDispatcher\EventDispatcherInterface;

/**
 * Authorization Code Grant Handler.
 *
 * Implements the OAuth2 Authorization Code grant type (RFC 6749 Section 4.1).
 * Supports PKCE (Proof Key for Code Exchange) according to RFC 7636.
 *
 * @see https://datatracker.ietf.org/doc/html/rfc6749#section-4.1
 * @see https://datatracker.ietf.org/doc/html/rfc7636
 */
final readonly class AuthorizationCodeGrantHandler implements GrantHandlerInterface
{
    public function __construct(
        private AuthorizationCodeRepositoryInterface $authorizationCodeRepository,
        private RefreshTokenRepositoryInterface $refreshTokenRepository,
        private JwtTokenGeneratorInterface $jwtTokenGenerator,
        private OpaqueTokenGeneratorInterface $opaqueTokenGenerator,
        private PkceValidatorInterface $pkceValidator,
        private IdentityFactoryInterface $identityFactory,
        private AuditLoggerInterface $auditLogger,
        private EventDispatcherInterface $eventDispatcher,
        private int $accessTokenTtl,
        private int $refreshTokenTtl,
    ) {
    }

    public function supports(GrantType $grantType): bool
    {
        return GrantType::AUTHORIZATION_CODE === $grantType;
    }

    /**
     * @throws InvalidRequestException
     * @throws InvalidGrantException
     */
    /**
     * @param array<string, mixed> $parameters
     */
    public function handle(array $parameters, OAuthClient $client): TokenResponseDTO
    {
        // 1. Extract and validate required parameters
        $code = $this->extractCode($parameters);
        $redirectUri = $this->extractRedirectUri($parameters);
        $codeVerifier = isset($parameters['code_verifier']) && is_string($parameters['code_verifier'])
            ? $parameters['code_verifier']
            : null;

        // 2. Retrieve authorization code
        $authorizationCode = $this->authorizationCodeRepository->findByCode($code);

        if (null === $authorizationCode) {
            $this->auditLogger->log(new AuditEventDTO(
                AuditEventTypeEnum::INVALID_GRANT,
                'warning',
                'Invalid authorization code provided',
                ['code' => substr($code, 0, 8) . '...'],
                null,
                $client->clientId
            ));

            throw new InvalidGrantException('Invalid authorization code');
        }

        // 3. Validate authorization code
        $this->validateAuthorizationCode($authorizationCode, $client, $redirectUri, $codeVerifier);

        // 4. Consume authorization code (single-use)
        $consumed = $this->authorizationCodeRepository->consume($code);

        if (!$consumed) {
            $this->auditLogger->log(new AuditEventDTO(
                AuditEventTypeEnum::SUSPICIOUS_ACTIVITY,
                'warning',
                'Authorization code replay attack detected',
                ['code' => substr($code, 0, 8) . '...'],
                null,
                $client->clientId
            ));

            throw new InvalidGrantException('Authorization code has already been used');
        }

        // 5. Generate access token (JWT)
        $scopeString = implode(' ', $authorizationCode->scopes);
        $jti = $this->identityFactory->generate();
        $payload = new JwtPayloadDTO(
            $authorizationCode->userId,
            $client->clientId,
            $scopeString,
            $this->accessTokenTtl,
            $client->clientId,
            $jti // Pass JTI to ensure it matches the one in the event
        );

        $accessToken = $this->jwtTokenGenerator->generate($payload);

        // 6. Generate refresh token (opaque)
        $refreshTokenValue = $this->opaqueTokenGenerator->generate();
        $refreshTokenId = $this->identityFactory->generate();
        $refreshToken = new OAuthRefreshToken(
            $refreshTokenId,
            $refreshTokenValue,
            $client->clientId,
            $authorizationCode->userId,
            $authorizationCode->scopes,
            false,
            (new \DateTimeImmutable())->modify(sprintf('+%d seconds', $this->refreshTokenTtl)),
            new \DateTimeImmutable()
        );

        $this->refreshTokenRepository->create($refreshToken);

        // 7. Dispatch domain events for audit logging
        /** @var list<string> $scopesList */
        $scopesList = array_values($authorizationCode->scopes);

        $this->eventDispatcher->dispatch(new AccessTokenIssuedEvent(
            $authorizationCode->userId,
            $client->clientId,
            GrantType::AUTHORIZATION_CODE->value,
            $scopesList,
            $jti
        ));

        $this->eventDispatcher->dispatch(new RefreshTokenIssuedEvent(
            $authorizationCode->userId,
            $client->clientId,
            $refreshTokenId,
            $scopesList
        ));

        // 8. Return token response
        return new TokenResponseDTO(
            $accessToken,
            'Bearer',
            $payload->expiresIn,
            $refreshTokenValue,
            $scopeString
        );
    }

    /**
     * Extract and validate authorization code from parameters.
     *
     * @param array<string, mixed> $parameters
     */
    private function extractCode(array $parameters): string
    {
        if (!isset($parameters['code'])) {
            throw new InvalidRequestException('Missing required parameter "code"');
        }

        if (!is_string($parameters['code'])) {
            throw new InvalidRequestException('The "code" parameter must be a string');
        }

        if (empty($parameters['code'])) {
            throw new InvalidRequestException('The "code" parameter cannot be empty');
        }

        return $parameters['code'];
    }

    /**
     * Extract and validate redirect_uri from parameters.
     *
     * @param array<string, mixed> $parameters
     */
    private function extractRedirectUri(array $parameters): string
    {
        if (!isset($parameters['redirect_uri'])) {
            throw new InvalidRequestException('Missing required parameter "redirect_uri"');
        }

        if (!is_string($parameters['redirect_uri'])) {
            throw new InvalidRequestException('The "redirect_uri" parameter must be a string');
        }

        if (empty($parameters['redirect_uri'])) {
            throw new InvalidRequestException('The "redirect_uri" parameter cannot be empty');
        }

        return $parameters['redirect_uri'];
    }

    /**
     * Validate authorization code against client and request parameters.
     *
     * @throws InvalidGrantException
     */
    private function validateAuthorizationCode(
        OAuthAuthorizationCode $authorizationCode,
        OAuthClient $client,
        string $redirectUri,
        ?string $codeVerifier,
    ): void {
        // Check expiration
        if ($authorizationCode->isExpired()) {
            $this->auditLogger->log(new AuditEventDTO(
                AuditEventTypeEnum::INVALID_GRANT,
                'warning',
                'Expired authorization code used',
                ['code' => substr($authorizationCode->code, 0, 8) . '...'],
                null,
                $client->clientId
            ));

            throw new InvalidGrantException('Authorization code has expired');
        }

        // Check client_id match
        if ($authorizationCode->clientId !== $client->clientId) {
            $this->auditLogger->log(new AuditEventDTO(
                AuditEventTypeEnum::SUSPICIOUS_ACTIVITY,
                'warning',
                'Authorization code client mismatch detected',
                [
                    'code' => substr($authorizationCode->code, 0, 8) . '...',
                    'expected_client' => $authorizationCode->clientId,
                    'actual_client' => $client->clientId,
                ],
                null,
                $client->clientId
            ));

            throw new InvalidGrantException('Authorization code was not issued to this client');
        }

        // Check redirect_uri match
        if ($authorizationCode->redirectUri !== $redirectUri) {
            $this->auditLogger->log(new AuditEventDTO(
                AuditEventTypeEnum::SUSPICIOUS_ACTIVITY,
                'warning',
                'Authorization code redirect URI mismatch',
                [
                    'code' => substr($authorizationCode->code, 0, 8) . '...',
                    'expected_redirect_uri' => $authorizationCode->redirectUri,
                    'actual_redirect_uri' => $redirectUri,
                ],
                null,
                $client->clientId
            ));

            throw new InvalidGrantException('Redirect URI does not match the one used in authorization request');
        }

        // Validate PKCE if code challenge was used
        if (null !== $authorizationCode->codeChallenge) {
            $this->validatePkce($authorizationCode, $codeVerifier, $client);
        }
    }

    /**
     * Validate PKCE code verifier against code challenge.
     *
     * @throws InvalidGrantException
     */
    private function validatePkce(
        OAuthAuthorizationCode $authorizationCode,
        ?string $codeVerifier,
        OAuthClient $client,
    ): void {
        if (null === $codeVerifier) {
            $this->auditLogger->log(new AuditEventDTO(
                AuditEventTypeEnum::INVALID_GRANT,
                'warning',
                'Missing PKCE code_verifier',
                ['code' => substr($authorizationCode->code, 0, 8) . '...'],
                null,
                $client->clientId
            ));

            throw new InvalidGrantException('PKCE code_verifier is required for this authorization code');
        }

        // At this point, codeChallenge is guaranteed to be non-null (checked in caller)
        if (null === $authorizationCode->codeChallengeMethod || null === $authorizationCode->codeChallenge) {
            throw new InvalidGrantException('Code challenge method is missing');
        }

        $isValid = $this->pkceValidator->validate(
            $codeVerifier,
            $authorizationCode->codeChallenge,
            $authorizationCode->codeChallengeMethod
        );

        if (!$isValid) {
            $this->auditLogger->log(new AuditEventDTO(
                AuditEventTypeEnum::INVALID_GRANT,
                'warning',
                'Invalid PKCE code_verifier provided',
                ['code' => substr($authorizationCode->code, 0, 8) . '...'],
                null,
                $client->clientId
            ));

            throw new InvalidGrantException('Invalid PKCE code_verifier');
        }
    }
}
