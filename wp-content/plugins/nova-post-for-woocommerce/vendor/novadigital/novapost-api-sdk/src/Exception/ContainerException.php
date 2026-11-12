<?php

declare(strict_types=1);

namespace NovaDigital\NovaPost\Exception;

use Exception;
use Psr\Container\ContainerExceptionInterface;

class ContainerException extends Exception implements ContainerExceptionInterface
{
    public static function circularDependency(string $serviceId): self
    {
        return new self(sprintf('Circular dependency detected for service "%s"', $serviceId));
    }

    public static function resolutionFailed(string $serviceId, \Throwable $previous): self
    {
        return new self(
            sprintf('Failed to resolve service "%s": %s', $serviceId, $previous->getMessage()),
            $previous->getCode(),
            $previous
        );
    }
}
