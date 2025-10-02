<?php

declare(strict_types=1);

namespace App\Repository;

use App\Model\OAuthAuthorizationCode;
use App\Service\TokenHasherInterface;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;

/**
 * Repository for OAuth2 authorization code management using Doctrine DBAL.
 *
 * This repository provides low-level database operations for OAuth2 authorization codes
 * using prepared statements for security and performance.
 */
final class AuthorizationCodeRepository implements AuthorizationCodeRepositoryInterface
{
    private const TABLE_NAME = 'oauth_authorization_codes';

    public function __construct(
        private readonly Connection $connection,
        private readonly TokenHasherInterface $tokenHasher,
    ) {
    }

    /**
     * {@inheritDoc}
     */
    public function create(OAuthAuthorizationCode $authorizationCode): void
    {
        $insertData = [
            'id' => $authorizationCode->id,
            'code_hash' => $this->tokenHasher->hash($authorizationCode->code),
            'client_id' => $authorizationCode->clientId,
            'user_id' => $authorizationCode->userId,
            'redirect_uri' => $authorizationCode->redirectUri,
            'scopes' => json_encode($authorizationCode->scopes),
            'code_challenge' => $authorizationCode->codeChallenge,
            'code_challenge_method' => $authorizationCode->codeChallengeMethod,
            'expires_at' => $authorizationCode->expiresAt->format('Y-m-d H:i:s'),
            'created_at' => $authorizationCode->createdAt->format('Y-m-d H:i:s'),
        ];

        try {
            $this->connection->insert(self::TABLE_NAME, $insertData);
        } catch (Exception $exception) {
            throw new \RuntimeException('Failed to create authorization code: ' . $exception->getMessage(), 0, $exception);
        }
    }

    /**
     * {@inheritDoc}
     */
    public function findByCode(string $code): ?OAuthAuthorizationCode
    {
        $codeHash = $this->tokenHasher->hash($code);

        $queryBuilder = $this->connection->createQueryBuilder();
        $queryBuilder
            ->select('*')
            ->from(self::TABLE_NAME)
            ->where('code_hash = :code_hash')
            ->setParameter('code_hash', $codeHash);

        try {
            $result = $queryBuilder->executeQuery()->fetchAssociative();

            if (false === $result) {
                return null;
            }

            return $this->hydrateAuthorizationCode($result, $code);
        } catch (Exception) {
            return null;
        }
    }

    /**
     * {@inheritDoc}
     */
    public function consume(string $code): bool
    {
        $codeHash = $this->tokenHasher->hash($code);

        try {
            $affectedRows = $this->connection->delete(
                self::TABLE_NAME,
                ['code_hash' => $codeHash]
            );

            return $affectedRows > 0;
        } catch (Exception) {
            return false;
        }
    }

    /**
     * {@inheritDoc}
     */
    public function deleteExpired(): int
    {
        $queryBuilder = $this->connection->createQueryBuilder();
        $queryBuilder
            ->delete(self::TABLE_NAME)
            ->where('expires_at < :now')
            ->setParameter('now', (new \DateTimeImmutable())->format('Y-m-d H:i:s'));

        try {
            return $queryBuilder->executeStatement();
        } catch (Exception) {
            return 0;
        }
    }

    /**
     * Hydrate OAuthAuthorizationCode from database row.
     *
     * @param array<string, mixed> $row Database row
     * @param string $plaintextCode Original plaintext code (not stored in DB)
     *
     * @throws \Exception
     */
    private function hydrateAuthorizationCode(array $row, string $plaintextCode): OAuthAuthorizationCode
    {
        $scopes = is_string($row['scopes']) ? json_decode($row['scopes'], true) : $row['scopes'];

        if (!is_array($scopes)) {
            $scopes = [];
        }

        // Ensure array is a list of strings
        $scopes = array_values(array_filter($scopes, 'is_string'));

        return new OAuthAuthorizationCode(
            id: is_string($row['id']) ? $row['id'] : '',
            code: $plaintextCode,
            clientId: is_string($row['client_id']) ? $row['client_id'] : '',
            userId: is_string($row['user_id']) ? $row['user_id'] : '',
            redirectUri: is_string($row['redirect_uri']) ? $row['redirect_uri'] : '',
            scopes: $scopes,
            codeChallenge: isset($row['code_challenge']) && is_string($row['code_challenge']) ? $row['code_challenge'] : null,
            codeChallengeMethod: isset($row['code_challenge_method']) && is_string($row['code_challenge_method']) ? $row['code_challenge_method'] : null,
            expiresAt: new \DateTimeImmutable(is_string($row['expires_at']) ? $row['expires_at'] : 'now'),
            createdAt: new \DateTimeImmutable(is_string($row['created_at']) ? $row['created_at'] : 'now'),
        );
    }
}
