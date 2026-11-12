<?php

declare(strict_types=1);

namespace NovaDigital\NovaPost\Http;

use NovaDigital\NovaPost\Exception\AuthenticationException;
use NovaDigital\NovaPost\Exception\TokenExpiredException;
use NovaDigital\NovaPost\Exception\TokenRefreshException;
use NovaDigital\NovaPost\Exception\ApiException;
use NovaDigital\NovaPost\TokenProviderInterface;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Log\LoggerInterface;
use Throwable;

class NovaPostClient implements ClientInterface
{
    public function __construct(
        private ClientInterface $httpClient,
        private TokenProviderInterface $tokenProvider,
        private ResponseValidatorInterface $responseValidator,
        private RetryHandlerInterface $retryHandler,
        private LoggerInterface $logger
    ) {
    }

    /**
     * @throws ApiException|TokenExpiredException|TokenRefreshException|AuthenticationException
     */
    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        try {
            $authRequest = $request->withHeader('Authorization', $this->tokenProvider->get());
            $response = $this->httpClient->sendRequest($authRequest);
            $this->responseValidator->validate($response);
            return $response;
        } catch (TokenExpiredException $e) {
            return $this->retryHandler->handleRetry($request, $e);
        } catch (ApiException $e) {
            throw $e;
        } catch (ClientExceptionInterface $e) {
            $this->logger->error('HTTP request failed', [
                'exception' => $e,
                'request_uri' => (string)$request->getUri(),
                'request_method' => $request->getMethod()
            ]);

            throw new ApiException('Request failed: ' . $e->getMessage(), $e->getCode(), $e);
        } catch (Throwable $e) {
            $this->logger->error('Unexpected error during request', [
                'exception' => $e,
                'request_uri' => (string)$request->getUri(),
                'request_method' => $request->getMethod()
            ]);

            throw new ApiException('Unexpected error while sending request', $e->getCode(), $e);
        }
    }
}
