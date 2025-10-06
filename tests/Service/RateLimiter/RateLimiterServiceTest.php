<?php

declare(strict_types=1);

namespace App\Tests\Service\RateLimiter;

use App\Infrastructure\RateLimiter\RateLimiterService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\Storage\InMemoryStorage;

final class RateLimiterServiceTest extends TestCase
{
    private RateLimiterFactory $oauthTokenLimiterFactory;
    private RateLimiterFactory $loginLimiterFactory;
    private RateLimiterFactory $globalIpLimiterFactory;
    private RateLimiterService $rateLimiterService;

    protected function setUp(): void
    {
        // Create real RateLimiterFactory instances with InMemoryStorage for testing
        $this->oauthTokenLimiterFactory = new RateLimiterFactory(
            [
                'id' => 'oauth_token_test',
                'policy' => 'sliding_window',
                'limit' => 20,
                'interval' => '1 minute',
            ],
            new InMemoryStorage(),
        );

        $this->loginLimiterFactory = new RateLimiterFactory(
            [
                'id' => 'login_test',
                'policy' => 'token_bucket',
                'limit' => 5,
                'rate' => [
                    'interval' => '5 minutes',
                    'amount' => 5,
                ],
            ],
            new InMemoryStorage(),
        );

        $this->globalIpLimiterFactory = new RateLimiterFactory(
            [
                'id' => 'global_ip_test',
                'policy' => 'sliding_window',
                'limit' => 100,
                'interval' => '1 minute',
            ],
            new InMemoryStorage(),
        );

        $this->rateLimiterService = new RateLimiterService(
            $this->oauthTokenLimiterFactory,
            $this->loginLimiterFactory,
            $this->globalIpLimiterFactory,
            '', // Empty whitelist by default
        );
    }

    public function testCheckOAuthTokenLimitReturnsAcceptedForFirstRequest(): void
    {
        $clientId = 'test-client-123';

        $result = $this->rateLimiterService->checkOAuthTokenLimit($clientId);

        $this->assertTrue($result->isAccepted());
        $this->assertSame(20, $result->getLimit());
        $this->assertSame(19, $result->getRemainingTokens());
    }

    public function testCheckOAuthTokenLimitDecrementsRemainingTokens(): void
    {
        $clientId = 'test-client-456';

        // First request
        $result1 = $this->rateLimiterService->checkOAuthTokenLimit($clientId);
        $this->assertSame(19, $result1->getRemainingTokens());

        // Second request
        $result2 = $this->rateLimiterService->checkOAuthTokenLimit($clientId);
        $this->assertSame(18, $result2->getRemainingTokens());

        // Third request
        $result3 = $this->rateLimiterService->checkOAuthTokenLimit($clientId);
        $this->assertSame(17, $result3->getRemainingTokens());
    }

    public function testCheckOAuthTokenLimitExceedsAfterMaxRequests(): void
    {
        $clientId = 'test-client-overflow';

        // Make 20 requests (the limit)
        for ($i = 0; $i < 20; ++$i) {
            $result = $this->rateLimiterService->checkOAuthTokenLimit($clientId);
            $this->assertTrue($result->isAccepted(), "Request {$i} should be accepted");
        }

        // 21st request should be rejected
        $result = $this->rateLimiterService->checkOAuthTokenLimit($clientId);
        $this->assertFalse($result->isAccepted());
        $this->assertSame(0, $result->getRemainingTokens());
    }

    public function testCheckLoginLimitReturnsAcceptedForFirstRequest(): void
    {
        $email = 'user@example.com';

        $result = $this->rateLimiterService->checkLoginLimit($email);

        $this->assertTrue($result->isAccepted());
        $this->assertSame(5, $result->getLimit());
        $this->assertSame(4, $result->getRemainingTokens());
    }

    public function testCheckLoginLimitExceedsAfterMaxAttempts(): void
    {
        $email = 'attacker@example.com';

        // Make 5 attempts (the limit)
        for ($i = 0; $i < 5; ++$i) {
            $result = $this->rateLimiterService->checkLoginLimit($email);
            $this->assertTrue($result->isAccepted(), "Attempt {$i} should be accepted");
        }

        // 6th attempt should be rejected
        $result = $this->rateLimiterService->checkLoginLimit($email);
        $this->assertFalse($result->isAccepted());
        $this->assertSame(0, $result->getRemainingTokens());
    }

    public function testCheckLoginLimitIsolatesPerEmail(): void
    {
        $email1 = 'user1@example.com';
        $email2 = 'user2@example.com';

        // User 1 makes 3 attempts
        for ($i = 0; $i < 3; ++$i) {
            $this->rateLimiterService->checkLoginLimit($email1);
        }

        // User 2 should still have full limit
        $result = $this->rateLimiterService->checkLoginLimit($email2);
        $this->assertTrue($result->isAccepted());
        $this->assertSame(4, $result->getRemainingTokens());
    }

    public function testCheckGlobalIpLimitReturnsAcceptedForFirstRequest(): void
    {
        $request = $this->createRequestWithIp('192.168.1.100');

        $result = $this->rateLimiterService->checkGlobalIpLimit($request);

        $this->assertTrue($result->isAccepted());
        $this->assertSame(100, $result->getLimit());
        $this->assertSame(99, $result->getRemainingTokens());
    }

    public function testCheckGlobalIpLimitWithUnknownIp(): void
    {
        $request = $this->createMock(Request::class);
        $request->method('getClientIp')->willReturn(null);

        $result = $this->rateLimiterService->checkGlobalIpLimit($request);

        $this->assertTrue($result->isAccepted());
        $this->assertSame(99, $result->getRemainingTokens());
    }

    public function testCheckGlobalIpLimitBypassesWhitelistedIps(): void
    {
        $whitelistedIp = '10.0.0.1';
        $rateLimiterService = new RateLimiterService(
            $this->oauthTokenLimiterFactory,
            $this->loginLimiterFactory,
            $this->globalIpLimiterFactory,
            $whitelistedIp,
        );

        $request = $this->createRequestWithIp($whitelistedIp);

        // Make 150 requests (more than the limit of 100)
        for ($i = 0; $i < 150; ++$i) {
            $result = $rateLimiterService->checkGlobalIpLimit($request);
            $this->assertTrue($result->isAccepted(), "Request {$i} should be accepted for whitelisted IP");
        }
    }

    public function testCheckGlobalIpLimitWithMultipleWhitelistedIps(): void
    {
        $whitelistedIps = '10.0.0.1, 10.0.0.2, 192.168.1.1';
        $rateLimiterService = new RateLimiterService(
            $this->oauthTokenLimiterFactory,
            $this->loginLimiterFactory,
            $this->globalIpLimiterFactory,
            $whitelistedIps,
        );

        // Test that all whitelisted IPs are recognized
        $this->assertTrue($rateLimiterService->isIpWhitelisted('10.0.0.1'));
        $this->assertTrue($rateLimiterService->isIpWhitelisted('10.0.0.2'));
        $this->assertTrue($rateLimiterService->isIpWhitelisted('192.168.1.1'));
        $this->assertFalse($rateLimiterService->isIpWhitelisted('192.168.1.100'));

        // Test that whitelisted IPs can exceed the limit
        $request = $this->createRequestWithIp('10.0.0.2');
        for ($i = 0; $i < 150; ++$i) {
            $result = $rateLimiterService->checkGlobalIpLimit($request);
            $this->assertTrue($result->isAccepted());
        }
    }

    public function testCheckGlobalIpLimitIsolatesPerIp(): void
    {
        $ip1 = '192.168.1.1';
        $ip2 = '192.168.1.2';

        // IP1 makes 50 requests
        $request1 = $this->createRequestWithIp($ip1);
        for ($i = 0; $i < 50; ++$i) {
            $this->rateLimiterService->checkGlobalIpLimit($request1);
        }

        // IP2 should still have full limit
        $request2 = $this->createRequestWithIp($ip2);
        $result = $this->rateLimiterService->checkGlobalIpLimit($request2);
        $this->assertTrue($result->isAccepted());
        $this->assertSame(99, $result->getRemainingTokens());
    }

    public function testIsIpWhitelistedReturnsFalseForEmptyWhitelist(): void
    {
        $this->assertFalse($this->rateLimiterService->isIpWhitelisted('10.0.0.1'));
        $this->assertFalse($this->rateLimiterService->isIpWhitelisted('192.168.1.1'));
    }

    public function testIsIpWhitelistedReturnsTrueForWhitelistedIp(): void
    {
        $rateLimiterService = new RateLimiterService(
            $this->oauthTokenLimiterFactory,
            $this->loginLimiterFactory,
            $this->globalIpLimiterFactory,
            '10.0.0.1, 192.168.1.100',
        );

        $this->assertTrue($rateLimiterService->isIpWhitelisted('10.0.0.1'));
        $this->assertTrue($rateLimiterService->isIpWhitelisted('192.168.1.100'));
        $this->assertFalse($rateLimiterService->isIpWhitelisted('192.168.1.1'));
    }

    public function testIsIpWhitelistedHandlesWhitespaceCorrectly(): void
    {
        $rateLimiterService = new RateLimiterService(
            $this->oauthTokenLimiterFactory,
            $this->loginLimiterFactory,
            $this->globalIpLimiterFactory,
            '  10.0.0.1  ,   192.168.1.100  ',
        );

        $this->assertTrue($rateLimiterService->isIpWhitelisted('10.0.0.1'));
        $this->assertTrue($rateLimiterService->isIpWhitelisted('192.168.1.100'));
    }

    public function testAddRateLimitHeadersAddsCorrectHeaders(): void
    {
        $response = new Response();
        $clientId = 'test-client';

        $rateLimit = $this->rateLimiterService->checkOAuthTokenLimit($clientId);
        $result = $this->rateLimiterService->addRateLimitHeaders($response, $rateLimit);

        $this->assertSame($response, $result);
        $this->assertSame('20', $response->headers->get('X-RateLimit-Limit'));
        $this->assertSame('19', $response->headers->get('X-RateLimit-Remaining'));
        $this->assertNull($response->headers->get('X-RateLimit-Retry-After'));
        $this->assertNull($response->headers->get('Retry-After'));
    }

    public function testAddRateLimitHeadersAddsRetryAfterWhenLimitExceeded(): void
    {
        $response = new Response();
        $clientId = 'test-client-exceeded';

        // Exhaust the limit
        for ($i = 0; $i < 20; ++$i) {
            $this->rateLimiterService->checkOAuthTokenLimit($clientId);
        }

        // Get rate limit for exceeded state
        $rateLimit = $this->rateLimiterService->checkOAuthTokenLimit($clientId);
        $result = $this->rateLimiterService->addRateLimitHeaders($response, $rateLimit);

        $this->assertSame($response, $result);
        $this->assertSame('20', $response->headers->get('X-RateLimit-Limit'));
        $this->assertSame('0', $response->headers->get('X-RateLimit-Remaining'));
        $this->assertNotNull($response->headers->get('X-RateLimit-Retry-After'));
        $this->assertNotNull($response->headers->get('Retry-After'));

        // Verify retry-after is a positive number
        $retryAfter = (int) $response->headers->get('Retry-After');
        $this->assertGreaterThan(0, $retryAfter);
    }

    /**
     * Create a Request mock with a specific client IP.
     */
    private function createRequestWithIp(string $ip): Request
    {
        $request = $this->createMock(Request::class);
        $request->method('getClientIp')->willReturn($ip);

        return $request;
    }
}
