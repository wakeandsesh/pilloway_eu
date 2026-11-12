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

declare(strict_types=1);

namespace NovaDigital\NovaPost;

use NovaDigital\NovaPost\Resources\ExchangeRate;
use NovaDigital\NovaPost\Resources\Subscription;
use NovaDigital\NovaPost\Resources\Webhooks;
use NovaDigital\NovaPost\Resources\Dictionary;
use NovaDigital\NovaPost\Resources\Division;
use NovaDigital\NovaPost\Resources\Shipment;
use NovaDigital\NovaPost\Resources\Pickup;
use NovaDigital\NovaPost\Resources\Registry;
use NovaDigital\NovaPost\Http\NovaPostClient;
use Psr\Container\ContainerInterface;

/**
 * @api
 */
final class NovaPostApi
{
    public const PRODUCTION_BASE_URL = 'https://api.novapost.com/v.1.0/';
    public const SANDBOX_BASE_URL = 'https://api-stage.novapost.pl/v.1.0/';

    private array $instances = [];

    public function __construct(
        private ContainerInterface $container
    ) {
    }

    /**
     * @api
     */
    public function dictionary(): Dictionary
    {
        return $this->getResource(Dictionary::class);
    }

    /**
     * @api
     */
    public function divisions(): Division
    {
        return $this->getResource(Division::class);
    }

    /**
     * @api
     */
    public function shipments(): Shipment
    {
        return $this->getResource(Shipment::class);
    }

    /**
     * @api
     */
    public function exchangeRates(): ExchangeRate
    {
        return $this->getResource(ExchangeRate::class);
    }

    /**
     * @api
     */
    public function pickups(): Pickup
    {
        return $this->getResource(Pickup::class);
    }

    /**
     * @api
     */
    public function subscriptions(): Subscription
    {
        return $this->getResource(Subscription::class);
    }

    /**
     * @api
     */
    public function webhooks(): Webhooks
    {
        return $this->getResource(Webhooks::class);
    }

    /**
     * @api
     */
    public function registry(): Registry
    {
        return $this->getResource(Registry::class);
    }

    private function getResource(string $class): object
    {
        if (isset($this->instances[$class])) {
            return $this->instances[$class];
        }

        if ($this->container->has($class)) {
            $instance = $this->container->get($class);
        } else {
            $instance = new $class($this->container->get(NovaPostClient::class));
        }

        return $this->instances[$class] = $instance;
    }
}
