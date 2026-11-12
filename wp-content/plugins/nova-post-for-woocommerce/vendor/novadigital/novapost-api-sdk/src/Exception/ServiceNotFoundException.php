<?php

declare(strict_types=1);

namespace NovaDigital\NovaPost\Exception;

use Exception;
use Psr\Container\NotFoundExceptionInterface;

class ServiceNotFoundException extends Exception implements NotFoundExceptionInterface
{
    public function __construct(string $serviceId)
    {
        parent::__construct(sprintf('Service "%s" not found in container', $serviceId));
    }
}
