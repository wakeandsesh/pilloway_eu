<?php

declare(strict_types=1);

namespace NovaDigital\NovaPost\Tests\Unit\Http;

use NovaDigital\NovaPost\Exception\ApiException;
use NovaDigital\NovaPost\Exception\AuthenticationException;
use NovaDigital\NovaPost\Exception\TokenExpiredException;
use NovaDigital\NovaPost\Exception\TokenRefreshException;
use NovaDigital\NovaPost\Http\NovaPostClient;
use NovaDigital\NovaPost\Http\ResponseValidatorInterface;
use NovaDigital\NovaPost\Http\RetryHandlerInterface;
use NovaDigital\NovaPost\TokenProviderInterface;
use GuzzleHttp\Handler\MockHandler;
use PHPUnit\Framework\TestCase;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\HandlerStack;
use Psr\Log\LoggerInterface;
use GuzzleHttp\Client;

final class NovaPostClientTest extends TestCase
{
    private TokenProviderInterface $tokenProvider;
    private MockHandler $mockHandler;
    private NovaPostClient $client;
    private ResponseValidatorInterface $responseValidator;
    private RetryHandlerInterface $retryHandler;
    private LoggerInterface $logger;
    private Client $httpClient;

    protected function setUp(): void
    {
        $this->mockHandler = new MockHandler();
        $handlerStack = HandlerStack::create($this->mockHandler);
        $this->httpClient = new Client(['handler' => $handlerStack]);

        $this->logger = $this->createMock(LoggerInterface::class);
        $this->tokenProvider = $this->createMock(TokenProviderInterface::class);
        $this->responseValidator = $this->createMock(ResponseValidatorInterface::class);
        $this->retryHandler = $this->createMock(RetryHandlerInterface::class);

        $this->client = new NovaPostClient(
            $this->httpClient,
            $this->tokenProvider,
            $this->responseValidator,
            $this->retryHandler,
            $this->logger
        );
    }

    public function testSendsRequestWithAuthorizationHeader(): void
    {
        $this->tokenProvider->method('get')->willReturn('jwt-token');
        $this->responseValidator->expects($this->once())
            ->method('validate')
            ->willReturnCallback(function ($response) {
                // No exception means validation passed
            });

        $this->mockHandler->append(new Response(200, [], json_encode(['data' => 'test'])));

        $response = $this->client->sendRequest(new Request('GET', 'https://example.com/test'));
        $request = $this->mockHandler->getLastRequest();

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('jwt-token', $request->getHeaderLine('Authorization'));
        $this->assertJsonStringEqualsJsonString('{"data":"test"}', (string) $response->getBody());
    }

    public function testHandlesTokenExpirationWithRetry(): void
    {
        $this->tokenProvider->method('get')
            ->willReturnOnConsecutiveCalls('expired-token', 'refreshed-token');

        $errorResponse = new Response(401, [], json_encode(['message' => 'Token expired']));
        $successResponse = new Response(200, [], json_encode(['data' => 'success']));

        $this->mockHandler->append($errorResponse, $successResponse);

        $validateCalls = 0;
        $this->responseValidator->expects($this->exactly(2))
            ->method('validate')
            ->willReturnCallback(function () use (&$validateCalls) {
                $validateCalls++;
                if ($validateCalls === 1) {
                    throw new TokenExpiredException('Token expired');
                }
                // Second validation passes
            });

        $this->retryHandler->expects($this->once())
            ->method('handleRetry')
            ->willReturnCallback(function () use ($successResponse) {
                // Create a new client for the retry to ensure validation happens again
                $retryClient = new NovaPostClient(
                    $this->httpClient,
                    $this->tokenProvider,
                    $this->responseValidator,
                    $this->retryHandler,
                    $this->logger
                );

                return $retryClient->sendRequest(
                    new Request('GET', 'https://example.com/secure', [
                        'Authorization' => 'refreshed-token'
                    ])
                );
            });

        $response = $this->client->sendRequest(new Request('GET', 'https://example.com/secure'));

        $this->assertSame(200, $response->getStatusCode());
        $this->assertJsonStringEqualsJsonString('{"data":"success"}', (string) $response->getBody());
        $this->assertSame(2, $validateCalls, 'Expected validate() to be called twice');
    }

    public function testThrowsApiExceptionOnClientError(): void
    {
        $this->tokenProvider->method('get')->willReturn('valid-token');

        $errorResponse = new Response(400, [], json_encode(['error' => 'Bad Request']));
        $this->mockHandler->append($errorResponse);

        $this->responseValidator->expects($this->once())
            ->method('validate')
            ->willThrowException(new ApiException('Bad Request', 400));

        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('Bad Request');
        $this->client->sendRequest(new Request('GET', 'https://example.com/error'));
    }

    public function testThrowsTokenRefreshExceptionWhenRefreshFails(): void
    {
        $this->tokenProvider->method('get')->willReturn('expired-token');

        $errorResponse = new Response(401, [], json_encode(['message' => 'Token expired']));
        $this->mockHandler->append($errorResponse);

        $this->responseValidator->expects($this->once())
            ->method('validate')
            ->willThrowException(new TokenExpiredException('Token expired'));

        $this->retryHandler->expects($this->once())
            ->method('handleRetry')
            ->willThrowException(new TokenRefreshException('Failed to refresh token'));

        $this->expectException(TokenRefreshException::class);
        $this->expectExceptionMessage('Failed to refresh token');
        $this->client->sendRequest(new Request('GET', 'https://example.com/secure'));
    }

    public function testHandlesServerError(): void
    {
        $this->tokenProvider->method('get')->willReturn('valid-token');

        $errorResponse = new Response(500, [], json_encode(['error' => 'Internal Server Error']));
        $this->mockHandler->append($errorResponse);

        $this->responseValidator->expects($this->once())
            ->method('validate')
            ->willThrowException(new ApiException('Server error', 500));

        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('Server error');
        $this->client->sendRequest(new Request('GET', 'https://example.com/server-error'));
    }

    public function testHandlesAuthenticationException(): void
    {
        $this->tokenProvider->method('get')->willReturn('invalid-token');

        $errorResponse = new Response(401, [], json_encode(['message' => 'Invalid credentials']));
        $this->mockHandler->append($errorResponse);

        $this->responseValidator->expects($this->once())
            ->method('validate')
            ->willThrowException(new AuthenticationException('Invalid credentials'));

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('Invalid credentials');
        $this->client->sendRequest(new Request('GET', 'https://example.com/auth'));
    }

    public function testHandlesDifferentHttpMethods(): void
    {
        $methods = ['GET', 'POST', 'PUT', 'DELETE', 'PATCH'];
        $this->tokenProvider->method('get')->willReturn('jwt-token');

        $this->responseValidator->expects($this->exactly(count($methods)))
            ->method('validate');

        foreach ($methods as $method) {
            $this->mockHandler->append(new Response(200, [], json_encode(['method' => $method])));
            $response = $this->client->sendRequest(new Request($method, 'https://example.com/test'));
            $request = $this->mockHandler->getLastRequest();

            $this->assertSame($method, $request->getMethod());
            $this->assertJsonStringEqualsJsonString(
                json_encode(['method' => $method]),
                (string) $response->getBody()
            );
        }
    }

    public function testLogsErrorOnClientException(): void
    {
        $this->tokenProvider->method('get')->willReturn('valid-token');

        // Add a response that will cause a runtime exception
        $this->mockHandler->append(new Response(200, [], 'invalid-json'));

        // The Client class should wrap the RuntimeException in an ApiException
        $this->responseValidator->expects($this->once())
            ->method('validate')
            ->willThrowException(new \RuntimeException('Network error'));

        $this->logger->expects($this->once())
            ->method('error')
            ->with(
                'Unexpected error during request',
                $this->callback(function ($context) {
                    return isset($context['exception']) &&
                        $context['exception']->getMessage() === 'Network error';
                })
            );

        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('Unexpected error while sending request');
        $this->client->sendRequest(new Request('GET', 'https://example.com/network-error'));
    }
}
