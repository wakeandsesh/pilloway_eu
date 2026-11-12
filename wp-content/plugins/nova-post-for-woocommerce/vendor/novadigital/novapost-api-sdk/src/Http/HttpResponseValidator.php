<?php

declare(strict_types=1);

namespace NovaDigital\NovaPost\Http;

use NovaDigital\NovaPost\Exception\AuthenticationException;
use NovaDigital\NovaPost\Exception\RateLimitException;
use NovaDigital\NovaPost\Exception\TokenExpiredException;
use NovaDigital\NovaPost\Exception\ApiException;
use Psr\Http\Message\ResponseInterface;

class HttpResponseValidator implements ResponseValidatorInterface
{
    /**
     * @throws ApiException|TokenExpiredException
     */
    public function validate(ResponseInterface $response): void
    {
        $statusCode = $response->getStatusCode();

        if ($statusCode < 400 || $statusCode === 422) {
            return;
        }

        match ($statusCode) {
            401 => $this->handleUnauthorized($statusCode),
            403 => $this->handleForbidden($statusCode),
            429 => $this->handleTooManyRequests($statusCode),
            default => $statusCode >= 500
                ? $this->handleServerError($statusCode)
                : $this->handleClientError($statusCode)
        };
    }

    /**
     * @throws TokenExpiredException
     */
    private function handleUnauthorized(int $statusCode): void
    {
        throw new TokenExpiredException('Token expired or invalid', $statusCode);
    }

    /**
     * @throws AuthenticationException
     */
    private function handleForbidden(int $statusCode): void
    {
        throw new AuthenticationException('Access forbidden - insufficient permissions', $statusCode);
    }

    /**
     * @throws ApiException
     */
    private function handleTooManyRequests(int $statusCode): void
    {
        throw new RateLimitException('Too many requests - please try again later', $statusCode);
    }

    /**
     * @throws ApiException
     */
    private function handleServerError(int $statusCode): void
    {
        throw new ApiException('Server error - please try again later', $statusCode);
    }

    /**
     * @throws ApiException
     */
    private function handleClientError(int $statusCode): void
    {
        throw new ApiException(sprintf('Request failed with status %d', $statusCode), $statusCode);
    }
}
