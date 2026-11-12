<?php

declare(strict_types=1);

namespace NovaDigital\NovaPost\Http;

use NovaDigital\NovaPost\Exception\AuthenticationException;
use NovaDigital\NovaPost\Exception\ApiException;

interface AuthClientInterface
{
    /**
     * @throws AuthenticationException|ApiException
     */
    public function getToken(): string;
}
