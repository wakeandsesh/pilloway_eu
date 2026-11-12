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

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\HttpFactory;
use NovaDigital\NovaPost\Http\AuthClient;
use NovaDigital\NovaPost\Http\AuthClientInterface;
use NovaDigital\NovaPost\Http\HttpResponseValidator;
use NovaDigital\NovaPost\Http\ResponseValidatorInterface;
use NovaDigital\NovaPost\Http\RetryHandlerInterface;
use NovaDigital\NovaPost\Http\TokenRetryHandler;
use NovaDigital\NovaPost\JwtTokenProvider;
use NovaDigital\NovaPost\NovaPostApi;
use NovaDigital\NovaPost\Storage\FileJwtTokenStorage;
use NovaDigital\NovaPost\Storage\JwtTokenStorageInterface;
use NovaDigital\NovaPost\TokenProviderInterface;
use Psr\Container\ContainerInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

class ContainerBuilder
{
    private array $bindings = [];
    private array $instances = [];
    private array $parameters = [];

    public function bind(string $abstract, string $concrete): self
    {
        $this->bindings[$abstract] = $concrete;
        return $this;
    }

    public function instance(string $id, object $instance): self
    {
        $this->instances[$id] = $instance;
        return $this;
    }

    public function setParameter(string $key, mixed $value): self
    {
        $this->parameters[$key] = $value;
        return $this;
    }

    public function build(): ContainerInterface
    {
        $this->loadDefaults();
        return new Container($this->bindings, $this->instances, $this->parameters);
    }

    private function loadDefaults(): void
    {
        $this->initHttpClient();
        $this->bindings = array_merge([
            LoggerInterface::class => NullLogger::class,
            ClientInterface::class => Client::class,
            RequestFactoryInterface::class => HttpFactory::class,
            AuthClientInterface::class => AuthClient::class,
            JwtTokenStorageInterface::class => FileJwtTokenStorage::class,
            TokenProviderInterface::class => JwtTokenProvider::class,
            ResponseValidatorInterface::class => HttpResponseValidator::class,
            RetryHandlerInterface::class => TokenRetryHandler::class,
        ], $this->bindings);
    }

    private function initHttpClient(): void
    {
        if (isset($this->instances[ClientInterface::class])) {
            return;
        }

        $config = array_merge(
            [
                'base_uri' => $this->parameters['useSandbox']
                    ? NovaPostApi::SANDBOX_BASE_URL
                    : NovaPostApi::PRODUCTION_BASE_URL
            ],
            $this->parameters['config'] ?? [],
        );
        $this->instance(ClientInterface::class, new Client($config));
    }
}
