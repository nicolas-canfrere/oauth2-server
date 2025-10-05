<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Controller\Example;

use App\Service\RateLimiter\RateLimiterServiceInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Example controller demonstrating rate limiting usage.
 * This file shows how to integrate rate limiting in your endpoints.
 *
 * @internal This is an example file for documentation purposes
 */
final class RateLimitedControllerExample extends AbstractController
{
    public function __construct(
        private readonly RateLimiterServiceInterface $rateLimiterService,
    ) {
    }

    /**
     * Example: OAuth token endpoint with rate limiting by client_id.
     *
     * Rate limit: 20 requests per minute per client_id
     * Strategy: Sliding window
     */
    #[Route('/oauth/token', name: 'oauth_token', methods: ['POST'])]
    public function oauthToken(Request $request): JsonResponse
    {
        // Extract client_id from request (from POST body or Basic Auth)
        $clientId = (string) $request->request->get('client_id', 'default');

        // Check rate limit for this client_id
        $rateLimit = $this->rateLimiterService->checkOAuthTokenLimit($clientId);

        // Create response
        $response = new JsonResponse([
            'access_token' => 'example_token',
            'token_type' => 'Bearer',
            'expires_in' => 3600,
        ]);

        // Add rate limit headers to response
        $this->rateLimiterService->addRateLimitHeaders($response, $rateLimit);

        // If rate limit exceeded, throw exception with headers
        if (!$rateLimit->isAccepted()) {
            throw new TooManyRequestsHttpException(
                $rateLimit->getRetryAfter()->getTimestamp() - time(),
                'Rate limit exceeded for client',
            );
        }

        return $response;
    }

    /**
     * Example: Login endpoint with rate limiting by email.
     *
     * Rate limit: 5 attempts per 5 minutes per email
     * Strategy: Token bucket
     */
    #[Route('/login', name: 'login', methods: ['POST'])]
    public function login(Request $request): JsonResponse
    {
        // Extract email from request
        $email = (string) $request->request->get('email', '');

        // Check rate limit for this email
        $rateLimit = $this->rateLimiterService->checkLoginLimit($email);

        // Create response
        $response = new JsonResponse([
            'success' => true,
            'message' => 'Login successful',
        ]);

        // Add rate limit headers
        $this->rateLimiterService->addRateLimitHeaders($response, $rateLimit);

        // If rate limit exceeded, throw exception
        if (!$rateLimit->isAccepted()) {
            throw new TooManyRequestsHttpException(
                $rateLimit->getRetryAfter()->getTimestamp() - time(),
                'Too many login attempts. Please try again later.',
            );
        }

        // Proceed with authentication logic...

        return $response;
    }

    /**
     * Example: Global API endpoint with IP-based rate limiting.
     *
     * Rate limit: 100 requests per minute per IP
     * Strategy: Sliding window
     * Note: Whitelisted IPs are automatically bypassed
     */
    #[Route('/api/resource', name: 'api_resource', methods: ['GET'])]
    public function apiResource(Request $request): JsonResponse
    {
        // Check global IP rate limit
        $rateLimit = $this->rateLimiterService->checkGlobalIpLimit($request);

        // Create response
        $response = new JsonResponse([
            'data' => ['example' => 'data'],
        ]);

        // Add rate limit headers
        $this->rateLimiterService->addRateLimitHeaders($response, $rateLimit);

        // If rate limit exceeded and IP is not whitelisted
        if (!$rateLimit->isAccepted()) {
            throw new TooManyRequestsHttpException(
                $rateLimit->getRetryAfter()->getTimestamp() - time(),
                'Rate limit exceeded. Please slow down your requests.',
            );
        }

        // Process request...

        return $response;
    }

    /**
     * Example: Combined rate limiting (multiple limiters).
     *
     * This endpoint checks both client_id and global IP rate limits.
     */
    #[Route('/api/protected', name: 'api_protected', methods: ['POST'])]
    public function protectedEndpoint(Request $request): JsonResponse
    {
        $clientId = (string) $request->headers->get('X-Client-ID', 'default');

        // Check both rate limits
        $clientRateLimit = $this->rateLimiterService->checkOAuthTokenLimit($clientId);
        $ipRateLimit = $this->rateLimiterService->checkGlobalIpLimit($request);

        // Create response
        $response = new JsonResponse([
            'status' => 'success',
        ]);

        // Add headers from both rate limiters (IP limit takes precedence for display)
        $this->rateLimiterService->addRateLimitHeaders($response, $ipRateLimit);

        // Check both limits
        if (!$clientRateLimit->isAccepted()) {
            throw new TooManyRequestsHttpException(
                $clientRateLimit->getRetryAfter()->getTimestamp() - time(),
                'Client rate limit exceeded',
            );
        }

        if (!$ipRateLimit->isAccepted()) {
            throw new TooManyRequestsHttpException(
                $ipRateLimit->getRetryAfter()->getTimestamp() - time(),
                'IP rate limit exceeded',
            );
        }

        // Process request...

        return $response;
    }

    /**
     * Example: Graceful rate limit handling without throwing exception.
     *
     * Instead of throwing an exception, return a custom response.
     */
    #[Route('/api/graceful', name: 'api_graceful', methods: ['GET'])]
    public function gracefulRateLimit(Request $request): JsonResponse
    {
        $rateLimit = $this->rateLimiterService->checkGlobalIpLimit($request);

        if (!$rateLimit->isAccepted()) {
            $response = new JsonResponse(
                [
                    'error' => 'rate_limit_exceeded',
                    'message' => 'You have exceeded the rate limit. Please try again later.',
                    'retry_after' => $rateLimit->getRetryAfter()->getTimestamp() - time(),
                ],
                Response::HTTP_TOO_MANY_REQUESTS,
            );

            $this->rateLimiterService->addRateLimitHeaders($response, $rateLimit);

            return $response;
        }

        $response = new JsonResponse(['data' => 'success']);
        $this->rateLimiterService->addRateLimitHeaders($response, $rateLimit);

        return $response;
    }
}
