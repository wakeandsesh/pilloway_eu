<?php

declare(strict_types=1);

namespace NovaDigital\NovaPost\Http;

use NovaDigital\NovaPost\Exception\AuthenticationException;
use NovaDigital\NovaPost\Exception\ApiException;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Log\LoggerInterface;
use Throwable;

class AuthClient implements AuthClientInterface
{
    private const ENDPOINT_TEMPLATE = 'clients/authorization?apiKey=%s';
    private const HTTP_STATUS_MESSAGES = [
        401 => 'Invalid API key or unauthorized access',
        403 => 'Access forbidden - check API key permissions',
        429 => 'Rate limit exceeded - please try again later'
    ];

    public function __construct(
        private ClientInterface $httpClient,
        private RequestFactoryInterface $requestFactory,
        private LoggerInterface $logger,
        private string $apiKey
    ) {
    }

    /**
     * @throws AuthenticationException|ApiException
     */
    public function getToken(): string
    {
        try {
            $request = $this->createAuthRequest();
            $response = $this->httpClient->sendRequest($request);

            $this->validateHttpResponse($response->getStatusCode());

            return $this->extractTokenFromResponse($response);
        } catch (AuthenticationException | ApiException $e) {
            throw $e;
        } catch (ClientExceptionInterface $e) {
            $this->logger->error('Authentication request failed', [
                'exception' => $e,
                'endpoint' => sprintf(self::ENDPOINT_TEMPLATE, '[REDACTED]')
            ]);

            throw new AuthenticationException(
                'Authentication request failed: ' . $e->getMessage(),
                $e->getCode(),
                $e
            );
        } catch (Throwable $e) {
            $this->logger->error('Unexpected error during authentication', [
                'exception' => $e,
                'endpoint' => sprintf(self::ENDPOINT_TEMPLATE, '[REDACTED]')
            ]);

            throw new ApiException('Unexpected error during authentication', $e->getCode(), $e);
        }
    }

    private function createAuthRequest(): RequestInterface
    {
        return $this->requestFactory->createRequest(
            'GET',
            sprintf(self::ENDPOINT_TEMPLATE, $this->apiKey)
        );
    }

    /**
     * @throws AuthenticationException|ApiException
     */
    private function validateHttpResponse(int $statusCode): void
    {
        if ($statusCode < 400) {
            return;
        }

        $this->logger->error('Authentication failed', [
            'status_code' => $statusCode,
            'endpoint' => sprintf(self::ENDPOINT_TEMPLATE, '[REDACTED]')
        ]);

        $errorMessage = $this->getErrorMessage($statusCode);

        match (true) {
            $statusCode >= 500 => throw new ApiException($errorMessage, $statusCode),
            default => throw new AuthenticationException($errorMessage, $statusCode)
        };
    }

    private function getErrorMessage(int $statusCode): string
    {
        return self::HTTP_STATUS_MESSAGES[$statusCode]
            ?? sprintf('Authentication failed with status %d', $statusCode);
    }

    /**
     * @throws AuthenticationException|ApiException
     */
    private function extractTokenFromResponse(ResponseInterface $response): string
    {
        $content = $response->getBody()->getContents();
        $data = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $jsonError = json_last_error_msg();
            $this->logger->error('Authentication response JSON decode failed', [
                'error' => $jsonError,
                'content_length' => strlen($content)
            ]);

            throw new ApiException('Invalid JSON response from authentication server: ' . $jsonError);
        }

        if (empty($data['jwt'])) {
            $this->logger->error('JWT token not found in authentication response', [
                'available_keys' => array_keys($data)
            ]);

            throw new AuthenticationException('Authentication failed: JWT token not found in response');
        }

        return $data['jwt'];
    }
}
