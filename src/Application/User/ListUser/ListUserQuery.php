<?php

declare(strict_types=1);

namespace App\Application\User\ListUser;

final readonly class ListUserQuery
{
    /**
     * Query for listing users with pagination.
     *
     * @param int    $page         Current page number (1-indexed, minimum: 1)
     * @param int    $itemsPerPage Number of items per page (minimum: 1, maximum: 100)
     * @param string $orderBy      Sort direction: 'asc' or 'desc'
     * @param string $sortField    Field to sort by: 'email', 'created_at', or 'id'
     *
     * @throws \InvalidArgumentException If validation fails
     */
    public function __construct(
        public int $page = 1,
        public int $itemsPerPage = 10,
        public string $orderBy = 'asc',
        public string $sortField = 'email',
    ) {
        if ($page < 1) {
            throw new \InvalidArgumentException('Page must be greater than or equal to 1.');
        }

        if ($itemsPerPage < 1 || $itemsPerPage > 100) {
            throw new \InvalidArgumentException('Items per page must be between 1 and 100.');
        }

        $normalizedOrderBy = strtolower($orderBy);
        if (!\in_array($normalizedOrderBy, ['asc', 'desc'], true)) {
            throw new \InvalidArgumentException('Order direction must be "asc" or "desc".');
        }

        $allowedFields = ['email', 'created_at', 'id'];
        if (!\in_array($sortField, $allowedFields, true)) {
            throw new \InvalidArgumentException(
                sprintf('Invalid sort field "%s". Allowed fields: %s', $sortField, implode(', ', $allowedFields))
            );
        }
    }
}
