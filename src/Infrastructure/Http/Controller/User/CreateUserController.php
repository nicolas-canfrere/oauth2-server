<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Controller\User;

use App\Application\User\CreateUser\CreateUserCommand;
use App\Application\User\CreateUser\CreateUserCommandHandler;
use App\Domain\User\UserAlreadyExistsException;
use Nelmio\ApiDocBundle\Attribute\Security;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

/**
 * User management endpoints.
 */
final class CreateUserController extends AbstractController
{
    public function __construct(
        private readonly CreateUserCommandHandler $createUserHandler,
    ) {
    }

    /**
     * Create a new user.
     */
    #[OA\Post(
        path: '/api/users',
        summary: 'Create a new user',
        tags: ['Users']
    )]
    #[OA\Response(
        response: 201,
        description: 'User created successfully',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'user_id', type: 'string', format: 'uuid', example: '550e8400-e29b-41d4-a716-446655440000'),
                new OA\Property(property: 'email', type: 'string', format: 'email', example: 'user@example.com'),
                new OA\Property(
                    property: 'roles',
                    type: 'array',
                    items: new OA\Items(type: 'string'),
                    example: ['ROLE_USER']
                ),
                new OA\Property(property: 'is_two_factor_enabled', type: 'boolean', example: false),
            ],
            type: 'object'
        )
    )]
    #[OA\Response(
        response: 400,
        description: 'Invalid request data',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'error', type: 'string', example: 'Validation failed'),
            ],
            type: 'object'
        )
    )]
    #[OA\Response(
        response: 409,
        description: 'User already exists',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'error', type: 'string', example: 'User with email "user@example.com" already exists.'),
            ],
            type: 'object'
        )
    )]
    #[Security(name: 'Bearer')]
    #[Route('/api/users', name: 'api_users_create', methods: ['POST'])]
    public function __invoke(
        #[MapRequestPayload(
            acceptFormat: 'json',
            validationFailedStatusCode: Response::HTTP_BAD_REQUEST
        )]
        CreateUserDTO $dto,
    ): JsonResponse {
        try {
            $command = new CreateUserCommand(
                email: $dto->email,
                plainPassword: $dto->password,
                roles: $dto->roles,
                isTwoFactorEnabled: $dto->isTwoFactorEnabled,
                totpSecret: $dto->totpSecret,
            );

            $userId = ($this->createUserHandler)($command);

            return $this->json([
                'user_id' => $userId,
                'email' => $dto->email,
                'roles' => $dto->roles,
                'is_two_factor_enabled' => $dto->isTwoFactorEnabled,
            ], Response::HTTP_CREATED);
        } catch (UserAlreadyExistsException $e) {
            return $this->json([
                'error' => $e->getMessage(),
            ], $e->getCode());
        } catch (\InvalidArgumentException $e) {
            return $this->json([
                'error' => $e->getMessage(),
            ], Response::HTTP_BAD_REQUEST);
        }
    }
}
