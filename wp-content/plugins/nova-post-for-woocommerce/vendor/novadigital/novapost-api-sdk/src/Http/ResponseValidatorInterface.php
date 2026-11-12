<?php

declare(strict_types=1);

namespace NovaDigital\NovaPost\Http;

use NovaDigital\NovaPost\Exception\TokenExpiredException;
use NovaDigital\NovaPost\Exception\ApiException;
use Psr\Http\Message\ResponseInterface;

interface ResponseValidatorInterface
{
    /**
     * @throws TokenExpiredException
     * @throws ApiException
     */
    public function validate(ResponseInterface $response): void;
}
