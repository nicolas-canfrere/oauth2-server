<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Domain\Audit\Enum\AuditEventTypeEnum;
use App\Infrastructure\Persistance\Doctrine\Repository\AuditLogRepository;
use App\Infrastructure\Persistance\Doctrine\Repository\UserRepository;
use App\Tests\Helper\UserBuilder;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

/**
 * Integration tests for SecurityController.
 *
 * Tests form-based login authentication flow.
 */
final class SecurityControllerTest extends WebTestCase
{
    private KernelBrowser $client;
    private Connection $connection;
    private UserRepository $userRepository;
    private AuditLogRepository $auditLogRepository;

    protected function setUp(): void
    {
        $this->client = static::createClient();

        $container = static::getContainer();
        $this->connection = $container->get('doctrine.dbal.default_connection');
        $this->userRepository = new UserRepository($this->connection);
        $this->auditLogRepository = new AuditLogRepository($this->connection);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
    }

    public function testAdminLoginSuccessWithValidCredentials(): void
    {
        $passwordHash = password_hash('SecurePassword123!', PASSWORD_BCRYPT) ?: '';

        $user = UserBuilder::aUser()
            ->withId('123e4567-e89b-12d3-a456-426614174001')
            ->withEmail('admin@example.com')
            ->withPasswordHash($passwordHash)
            ->withRoles(['ROLE_USER', 'ROLE_ADMIN'])
            ->build();
        $this->userRepository->create($user);

        $this->client->request(
            'POST',
            '/admin/login',
            [
                '_username' => 'admin@example.com',
                '_password' => 'SecurePassword123!',
            ]
        );

        $this->assertResponseRedirects('/');

        // Verify audit log was created for successful login
        $auditLogs = $this->auditLogRepository->findByUserId('123e4567-e89b-12d3-a456-426614174001');
        $this->assertNotEmpty($auditLogs, 'Audit log should be created for successful login');

        $loginSuccessLog = null;
        foreach ($auditLogs as $log) {
            if (AuditEventTypeEnum::LOGIN_SUCCESS === $log->eventType) {
                $loginSuccessLog = $log;
                break;
            }
        }

        $this->assertNotNull($loginSuccessLog, 'LOGIN_SUCCESS audit event should exist');
        $this->assertSame('123e4567-e89b-12d3-a456-426614174001', $loginSuccessLog->userId);
        $this->assertSame('info', $loginSuccessLog->level);
        $this->assertStringContainsString('logged in successfully', $loginSuccessLog->message);
        $this->assertNotNull($loginSuccessLog->ipAddress);
    }

    public function testAdminLoginFailsWithInvalidPassword(): void
    {
        $passwordHash = password_hash('CorrectPassword123!', PASSWORD_BCRYPT) ?: '';

        $user = UserBuilder::aUser()
            ->withId('223e4567-e89b-12d3-a456-426614174002')
            ->withEmail('user@example.com')
            ->withPasswordHash($passwordHash)
            ->build();
        $this->userRepository->create($user);

        $this->client->request(
            'POST',
            '/admin/login',
            [
                '_username' => 'user@example.com',
                '_password' => 'WrongPassword123!',
            ]
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_FOUND);

        // Verify audit log was created for failed login
        $qb = $this->connection->createQueryBuilder();
        $qb->select('COUNT(*)')
            ->from('oauth_audit_logs')
            ->where('event_type = :event_type')
            ->setParameter('event_type', AuditEventTypeEnum::LOGIN_FAILURE->value);

        $failureCountResult = $qb->executeQuery()->fetchOne();
        $this->assertNotFalse($failureCountResult);
        /** @phpstan-ignore-next-line cast.int */
        $failureCount = (int) $failureCountResult;
        $this->assertGreaterThan(0, $failureCount, 'LOGIN_FAILURE audit event should be logged');
    }

    public function testAdminLoginFailsWithNonExistentUser(): void
    {
        $this->client->request(
            'POST',
            '/admin/login',
            [
                '_username' => 'nonexistent@example.com',
                '_password' => 'SomePassword123!',
            ]
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_FOUND);

        // Verify audit log was created for failed login with non-existent user
        $qb = $this->connection->createQueryBuilder();
        $qb->select('*')
            ->from('oauth_audit_logs')
            ->where('event_type = :event_type')
            ->setParameter('event_type', AuditEventTypeEnum::LOGIN_FAILURE->value)
            ->orderBy('created_at', 'DESC')
            ->setMaxResults(1);

        $result = $qb->executeQuery()->fetchAssociative();
        $this->assertNotFalse($result, 'LOGIN_FAILURE audit event should exist');
        $this->assertNull($result['user_id'], 'User ID should be null for non-existent user');
        $this->assertIsString($result['context']);
        // Note: form_login doesn't expose the username in context for security reasons
    }

    public function testAdminLoginFailsWithMissingEmail(): void
    {
        $this->client->request(
            'POST',
            '/admin/login',
            [
                '_password' => 'SomePassword123!',
            ]
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
    }

    public function testAdminLoginFailsWithMissingPassword(): void
    {
        $this->client->request(
            'POST',
            '/admin/login',
            [
                '_username' => 'test@example.com',
            ]
        );

        $this->assertResponseRedirects('/admin/login');
    }

    public function testAdminLoginRendersLoginPage(): void
    {
        $this->client->request('GET', '/admin/login');

        $this->assertResponseIsSuccessful();
        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
    }

    public function testAdminLoginWithRegularUserRole(): void
    {
        $passwordHash = password_hash('UserPassword123!', PASSWORD_BCRYPT) ?: '';

        $user = UserBuilder::aUser()
            ->withId('323e4567-e89b-12d3-a456-426614174003')
            ->withEmail('regular@example.com')
            ->withPasswordHash($passwordHash)
            ->build();
        $this->userRepository->create($user);

        $this->client->request(
            'POST',
            '/admin/login',
            [
                '_username' => 'regular@example.com',
                '_password' => 'UserPassword123!',
            ]
        );

        $this->assertResponseRedirects('/');
    }

    public function testAdminLoginAuthenticatesUserInSession(): void
    {
        $passwordHash = password_hash('SessionTest123!', PASSWORD_BCRYPT) ?: '';

        $user = UserBuilder::aUser()
            ->withId('423e4567-e89b-12d3-a456-426614174004')
            ->withEmail('session@example.com')
            ->withPasswordHash($passwordHash)
            ->withRoles(['ROLE_USER', 'ROLE_ADMIN'])
            ->build();
        $this->userRepository->create($user);

        // First login request
        $this->client->request(
            'POST',
            '/admin/login',
            [
                '_username' => 'session@example.com',
                '_password' => 'SessionTest123!',
            ]
        );

        $this->assertResponseRedirects('/');
    }

    public function testAdminLoginWithEmptyCredentials(): void
    {
        $this->client->request(
            'POST',
            '/admin/login',
            [
                '_username' => '',
                '_password' => '',
            ]
        );

        // Form login redirects back to login page on validation failure
        $this->assertResponseRedirects('/admin/login');
    }
}
