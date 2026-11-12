<?php

/**
 * Copyright (C) 2025 NovaDigital
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

namespace NovaDigital\NovaPost\DI;

use Psr\Container\ContainerInterface;
use ReflectionClass;
use ReflectionNamedType;

class Container implements ContainerInterface
{
    private array $bindings;
    private array $instances;
    private array $parameters;

    public function __construct(array $bindings, array $instances = [], array $parameters = [])
    {
        $this->bindings = $bindings;
        $this->instances = $instances;
        $this->parameters = $parameters;
    }

    public function get(string $id): object
    {
        if (isset($this->instances[$id])) {
            return $this->instances[$id];
        }

        $concrete = $this->bindings[$id] ?? $id;

        if (!class_exists($concrete)) {
            throw new \RuntimeException("Cannot resolve dependency for {$id}");
        }

        $reflection = new ReflectionClass($concrete);
        if (!$reflection->isInstantiable()) {
            throw new \RuntimeException("Class {$concrete} is not instantiable");
        }

        $constructor = $reflection->getConstructor();
        if (!$constructor) {
            $object = new $concrete();
            return $this->instances[$id] = $object;
        }

        $args = [];
        foreach ($constructor->getParameters() as $param) {
            $paramType = $param->getType();
            if ($paramType instanceof ReflectionNamedType && !$paramType->isBuiltin()) {
                $depClass = $paramType->getName();
                $args[] = $this->get($depClass);
            } elseif (array_key_exists($param->getName(), $this->parameters)) {
                $args[] = $this->parameters[$param->getName()];
            } elseif ($param->isDefaultValueAvailable()) {
                $args[] = $param->getDefaultValue();
            } else {
                throw new \RuntimeException("Cannot resolve parameter '{$param->getName()}' in {$concrete}");
            }
        }

        $object = $reflection->newInstanceArgs($args);
        $this->instances[$id] = $object;

        return $object;
    }

    public function has(string $id): bool
    {
        return isset($this->instances[$id]);
    }
}
