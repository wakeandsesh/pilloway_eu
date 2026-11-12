<?php

declare(strict_types=1);

namespace NovaDigital\NovaPost\Resources;

use GuzzleHttp\Psr7\Request;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Client\ClientExceptionInterface;

abstract class AbstractResource
{
    public function __construct(
        protected ClientInterface $client,
    ) {
    }

    /**
     * Send an HTTP request to the Nova Post API.
     *
     * @param string $method HTTP method (GET, POST, PUT, PATCH, DELETE)
     * @param string $uri API endpoint URI
     * @param array $data Request data (for POST, PUT, PATCH) or query parameters (for GET)
     * @return array|string API response data
     * @throws ClientExceptionInterface
     */
    protected function sendRequest(string $method, string $uri, array $data = []): array|string
    {
        $body = null;
        if (!empty($data) && in_array($method, ['POST', 'PUT', 'PATCH'])) {
            $body = json_encode($data);
        }

        if ($method === 'GET' && !empty($data)) {
            $uri .= '?' . http_build_query($data);
        }

        $request = new Request($method, $uri, [], $body);
        if ($body !== null) {
            $request = $request->withHeader('Content-Type', 'application/json')
                ->withHeader('User-Agent', 'NovaPost-SDK/1.0');
        }

        $response = $this->client->sendRequest($request);

        $contents = $response->getBody()->getContents();
        $decoded = json_decode($contents, true);

        return is_array($decoded) ? $decoded : $contents;
    }
}
