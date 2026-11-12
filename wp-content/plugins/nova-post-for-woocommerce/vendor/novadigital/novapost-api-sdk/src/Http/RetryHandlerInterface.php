<?php

declare(strict_types=1);

namespace NovaDigital\NovaPost\Http;

use Exception;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

interface RetryHandlerInterface
{
    public function shouldRetry(Exception $exception): bool;

    public function handleRetry(RequestInterface $request, Exception $exception): ResponseInterface;
}
