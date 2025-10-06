<?php

declare(strict_types=1);

namespace App\Infrastructure\Security;

use App\Application\AccessToken\Exception\OAuth2Exception;
use App\Domain\OAuthClient\Exception\InvalidClientException;
use App\Domain\OAuthClient\Security\ClientAuthenticatorInterface;
use App\Infrastructure\Security\User\OAuth2ClientUser;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;

/**
 * Symfony Security Authenticator for OAuth2 client authentication.
 *
 * Integrates ClientAuthenticator service with Symfony Security component
 * for protecting OAuth2 token endpoint.
 */
final class OAuth2ClientAuthenticator extends AbstractAuthenticator
{
    public function __construct(
        private readonly ClientAuthenticatorInterface $clientAuthenticator,
    ) {
    }

    /**
     * {@inheritDoc}
     */
    public function supports(Request $request): bool
    {
        return str_starts_with(
            $request->getPathInfo(),
            '/oauth/token'
        );
    }

    /**
     * {@inheritDoc}
     */
    public function authenticate(Request $request): Passport
    {
        try {
            $client = $this->clientAuthenticator->authenticate($request);
        } catch (InvalidClientException $e) {
            // Wrap the domain specific exception into a Symfony one to be handled by onAuthenticationFailure
            throw new CustomUserMessageAuthenticationException($e->getMessage(), [], $e->getCode(), $e);
        }

        // Create a Passport with the client as "user"
        // The client_id is used as user identifier
        return new SelfValidatingPassport(
            new UserBadge($client->clientId, function () use ($client) {
                // Return a SecurityUser representing the OAuth2 client
                return new OAuth2ClientUser($client);
            })
        );
    }

    /**
     * {@inheritDoc}
     */
    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        // Let the request continue to the controller
        // The controller will generate and return the OAuth2 token response
        return null;
    }

    /**
     * {@inheritDoc}
     */
    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): Response
    {
        $previous = $exception->getPrevious();

        if ($previous instanceof OAuth2Exception) {
            return new JsonResponse($previous->toArray(), $previous->getHttpStatus());
        }

        // Fallback for generic authentication errors
        return new JsonResponse([
            'error' => 'invalid_client',
            'error_description' => $exception->getMessageKey(),
        ], Response::HTTP_UNAUTHORIZED);
    }
}
