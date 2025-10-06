<?php

declare(strict_types=1);

namespace App\Infrastructure\RateLimiter;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimit;

interface RateLimiterServiceInterface
{
    /**
     * Check if request is rate limited for OAuth token endpoint.
     *
     * @param string $clientId Client identifier for rate limiting
     *
     * @return RateLimit Rate limit status
     */
    public function checkOAuthTokenLimit(string $clientId): RateLimit;

    /**
     * Check if request is rate limited for login attempts.
     *
     * @param string $email User email for rate limiting
     *
     * @return RateLimit Rate limit status
     */
    public function checkLoginLimit(string $email): RateLimit;

    /**
     * Check if request is rate limited globally by IP.
     * Whitelisted IPs are automatically accepted.
     *
     * @param Request $request HTTP request containing client IP
     *
     * @return RateLimit Rate limit status
     */
    public function checkGlobalIpLimit(Request $request): RateLimit;

    /**
     * Add rate limit headers to HTTP response.
     *
     * @param Response  $response HTTP response to add headers to
     * @param RateLimit $limit    Rate limit information
     *
     * @return Response Modified response with rate limit headers
     */
    public function addRateLimitHeaders(Response $response, RateLimit $limit): Response;

    /**
     * Check if IP address is whitelisted.
     *
     * @param string $ipAddress IP address to check
     *
     * @return bool True if IP is whitelisted, false otherwise
     */
    public function isIpWhitelisted(string $ipAddress): bool;
}
