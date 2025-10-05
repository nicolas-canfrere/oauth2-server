<?php

declare(strict_types=1);

namespace App\Infrastructure\Listener;

use App\Application\AccessToken\GrantHandler\GrantHandlerDispatcher;
use App\Infrastructure\Security\User\OAuth2ClientUser;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

#[AsEventListener]
final readonly class OAuthTokenListener
{
    public function __construct(
        private TokenStorageInterface $tokenStorage,
        private GrantHandlerDispatcher $grantHandlerDispatcher,
    ) {
    }

    public function __invoke(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }
        $request = $event->getRequest();
        if (!$request->isMethod(Request::METHOD_POST)) {
            return;
        }
        if (!str_starts_with($request->getPathInfo(), '/oauth/token')) {
            return;
        }
        $user = $this->tokenStorage->getToken()?->getUser();
        if (!$user instanceof OAuth2ClientUser) {
            return;
        }
        $tokenResponseDTO = $this->grantHandlerDispatcher->dispatch(
            $request->request->all(),
            $user->getClient(),
        );

        $response = new JsonResponse(
            $tokenResponseDTO->toArray(),
        );

        $event->setResponse($response);
    }
}
