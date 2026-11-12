<?php

declare(strict_types=1);

namespace NovaDigital\NovaPost\Http;

use NovaDigital\NovaPost\Exception\TokenExpiredException;
use NovaDigital\NovaPost\Exception\TokenRefreshException;
use NovaDigital\NovaPost\Exception\ApiException;
use NovaDigital\NovaPost\TokenProviderInterface;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Log\LoggerInterface;
use Exception;
use Throwable;

class TokenRetryHandler implements RetryHandlerInterface
{
    public function __construct(
        private ClientInterface $httpClient,
        private TokenProviderInterface $tokenProvider,
        private ResponseValidatorInterface $responseValidator,
        private LoggerInterface $logger
    ) {
    }

    public function shouldRetry(Exception $exception): bool
    {
        return $exception instanceof TokenExpiredException;
    }

    /**
     * @throws TokenRefreshException|ApiException|Exception
     */
    public function handleRetry(RequestInterface $request, Exception $exception): ResponseInterface
    {
        if (!$this->shouldRetry($exception)) {
            throw $exception;
        }

        try {
            $refreshedToken = $this->tokenProvider->refresh();
            $retryRequest = $request->withHeader('Authorization', $refreshedToken);

            $response = $this->httpClient->sendRequest($retryRequest);
            $this->responseValidator->validate($response);
            return $response;
        } catch (ApiException $e) {
            throw $e;
        } catch (ClientExceptionInterface $e) {
            $this->logger->error('Token refresh failed - HTTP client error', ['exception' => $e]);

            throw new ApiException('Request failed: ' . $e->getMessage(), $e->getCode(), $e);
        } catch (Throwable $e) {
            $this->logger->error('Unexpected error during token retry', ['exception' => $e]);

            throw new ApiException('Unexpected error during retry', $e->getCode(), $e);
        }
    }
}
