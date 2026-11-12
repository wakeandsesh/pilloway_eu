<?php

declare(strict_types=1);

namespace NovaDigital\NovaPost;

use Exception;
use NovaDigital\NovaPost\Exception\ApiException;
use NovaDigital\NovaPost\Exception\TokenExpiredException;
use NovaDigital\NovaPost\Exception\TokenRefreshException;
use NovaDigital\NovaPost\Http\AuthClientInterface;
use NovaDigital\NovaPost\Storage\JwtTokenStorageInterface;
use NovaDigital\NovaPost\Exception\AuthenticationException;

class JwtTokenProvider implements TokenProviderInterface
{
    private const JWT_TOKEN_EXPIRY_SKEW_SECONDS = 30;
    private const JWT_TOKEN_PARTS_COUNT = 3;

    private ?string $jwtToken = null;
    private ?int $tokenExpiry = null;
    public function __construct(
        private JwtTokenStorageInterface $jwtTokenStorage,
        private AuthClientInterface $authClient
    ) {
    }

    /**
     * Get a valid JWT token, fetching a new one if necessary.
     *
     * @throws TokenExpiredException When stored token is expired (triggers retry mechanism)
     * @throws AuthenticationException|ApiException When authentication fails
     */
    public function get(): string
    {
        if ($this->jwtToken !== null) {
            if ($this->isTokenExpired($this->jwtToken)) {
                $this->clearCachedToken();
            } else {
                return $this->jwtToken;
            }
        }

        $storedToken = $this->jwtTokenStorage->get();
        if ($storedToken !== null) {
            if ($this->isTokenExpired($storedToken)) {
                $this->jwtTokenStorage->delete();
                throw new TokenExpiredException('Stored token has expired');
            }

            $this->jwtToken = $storedToken;
            return $this->jwtToken;
        }

        $this->fetch();
        return $this->jwtToken;
    }

    /**
     * Force refresh of the JWT token.
     *
     * @throws TokenRefreshException When refresh fails
     */
    public function refresh(): string
    {
        try {
            $this->clearCachedToken();
            $this->jwtTokenStorage->delete();

            $this->fetch();
            return $this->jwtToken;
        } catch (Exception $e) {
            throw new TokenRefreshException(
                'Failed to refresh token: ' . $e->getMessage(),
                $e->getCode(),
                $e
            );
        }
    }

    /**
     * @throws AuthenticationException|ApiException
     */
    private function fetch(): void
    {
        $this->jwtToken = $this->authClient->getToken();
        $this->extractExpiry($this->jwtToken);

        if ($this->tokenExpiry === null) {
            throw new AuthenticationException('Invalid JWT token: missing or invalid expiry');
        }

        $this->jwtTokenStorage->save($this->jwtToken);
    }

    private function clearCachedToken(): void
    {
        $this->jwtToken = null;
        $this->tokenExpiry = null;
    }

    private function isTokenExpired(string $token): bool
    {
        if ($this->tokenExpiry === null) {
            $this->extractExpiry($token);
        }

        return $this->tokenExpiry === null
            || time() >= ($this->tokenExpiry - self::JWT_TOKEN_EXPIRY_SKEW_SECONDS);
    }

    private function extractExpiry(string $token): void
    {
        $parts = explode('.', $token);
        if (count($parts) !== self::JWT_TOKEN_PARTS_COUNT) {
            $this->tokenExpiry = null;
            return;
        }

        $payloadJson = base64_decode(strtr($parts[1], '-_', '+/'), true);
        if ($payloadJson === false) {
            $this->tokenExpiry = null;
            return;
        }

        $payload = json_decode($payloadJson, true);
        $this->tokenExpiry = (isset($payload['exp']) && is_int($payload['exp']))
            ? $payload['exp']
            : null;
    }
}
