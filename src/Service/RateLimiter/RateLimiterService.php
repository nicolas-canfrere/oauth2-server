<?php

declare(strict_types=1);

namespace App\Service\RateLimiter;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimit;
use Symfony\Component\RateLimiter\RateLimiterFactory;

final class RateLimiterService implements RateLimiterServiceInterface
{
    /**
     * @var array<string> Whitelisted IP addresses
     */
    private readonly array $whitelistedIps;

    public function __construct(
        private readonly RateLimiterFactory $oauthTokenLimiter,
        private readonly RateLimiterFactory $loginLimiter,
        private readonly RateLimiterFactory $globalIpLimiter,
        string $whitelistedIpsString,
    ) {
        // Parse comma-separated whitelist from environment variable
        $this->whitelistedIps = array_filter(
            array_map(
                static fn(string $ip): string => trim($ip),
                explode(',', $whitelistedIpsString),
            ),
        );
    }

    public function checkOAuthTokenLimit(string $clientId): RateLimit
    {
        $limiter = $this->oauthTokenLimiter->create($clientId);

        return $limiter->consume(1);
    }

    public function checkLoginLimit(string $email): RateLimit
    {
        $limiter = $this->loginLimiter->create($email);

        return $limiter->consume(1);
    }

    public function checkGlobalIpLimit(Request $request): RateLimit
    {
        $ipAddress = $request->getClientIp() ?? 'unknown';

        // Bypass rate limiting for whitelisted IPs
        if ($this->isIpWhitelisted($ipAddress)) {
            // Create a fake accepted rate limit for whitelisted IPs
            $limiter = $this->globalIpLimiter->create($ipAddress);

            return $limiter->consume(0); // Consume 0 tokens to always accept
        }

        $limiter = $this->globalIpLimiter->create($ipAddress);

        return $limiter->consume(1);
    }

    public function addRateLimitHeaders(Response $response, RateLimit $limit): Response
    {
        $headers = [
            'X-RateLimit-Limit' => (string) $limit->getLimit(),
            'X-RateLimit-Remaining' => (string) $limit->getRemainingTokens(),
        ];

        // Add retry-after header only if rate limit is exceeded
        if (!$limit->isAccepted()) {
            $retryAfter = $limit->getRetryAfter()->getTimestamp() - time();
            $headers['X-RateLimit-Retry-After'] = (string) max(0, $retryAfter);
            $headers['Retry-After'] = (string) max(0, $retryAfter);
        }

        $response->headers->add($headers);

        return $response;
    }

    public function isIpWhitelisted(string $ipAddress): bool
    {
        return in_array($ipAddress, $this->whitelistedIps, true);
    }
}
