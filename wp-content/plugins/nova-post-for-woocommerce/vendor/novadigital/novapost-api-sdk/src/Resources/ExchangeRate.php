<?php

declare(strict_types=1);

namespace NovaDigital\NovaPost\Resources;

use Psr\Http\Client\ClientExceptionInterface;

class ExchangeRate extends AbstractResource
{
    /**
     * Convert an amount from base currency to multiple currencies.
     *
     * Mandatory parameters:
     *  - countryCode (string) — Sender's ISO country code (e.g., 'PL')
     *  - currencyCode (string) — Base currency ISO code (e.g., 'USD')
     *  - amount (number) — Amount to convert. Must include 2 decimal places
     *  - date (string) — Operation ISO date (e.g., '2025-01-01T00:00:00.000000Z')
     *
     * @see https://api.novapost.com/developers/index.html#post-/exchange-rates/conversion
     *
     * @param array $params Request parameters
     * @return array
     * @throws ClientExceptionInterface
     */
    public function convert(array $params): array
    {
        return $this->sendRequest('POST', 'exchange-rates/conversion', $params);
    }
}
