<?php

declare(strict_types=1);

namespace App\Domain\OAuthClient\Exception;

use App\Application\AccessToken\Exception\OAuth2Exception;

class UnauthorizedClientException extends OAuth2Exception
{
    public function __construct(
        ?string $errorDescription = null,
        ?string $errorUri = null,
        int $code = 0,
        ?\Throwable $previous = null,
    ) {
        parent::__construct(
            'unauthorized_client',
            $errorDescription ?? 'The authenticated client is not authorized to use this authorization grant type.',
            $errorUri,
            403, // Forbidden
            $code,
            $previous
        );
    }
}
