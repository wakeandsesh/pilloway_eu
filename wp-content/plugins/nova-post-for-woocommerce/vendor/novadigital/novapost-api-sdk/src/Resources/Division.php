<?php

declare(strict_types=1);

namespace NovaDigital\NovaPost\Resources;

use Psr\Http\Client\ClientExceptionInterface;

class Division extends AbstractResource
{
    public const DIVISION_CATEGORY_CARGO_BRANCH = 'CargoBranch';
    public const DIVISION_CATEGORY_POST_BRANCH = 'PostBranch';
    public const DIVISION_CATEGORY_POSTOMAT = 'Postomat';
    public const DIVISION_CATEGORY_PUDO = 'PUDO';

    /**
     * Get a list of cargo warehouses and parcel lockers.
     *
     * Optional parameters:
     * - textSearch (string) — Free-text search query (e.g., 'Warsaw')
     * - countryCodes (array<string>) — Filter by ISO country codes.
     * Supported: CZ | DE | EE | ES | FR | GB | HU | IT | LT | LV | MD | NL | PL | RO | SK | UA
     * - divisionCategories (array<string>) — Filter by division categories (see constants).
     *
     * @see https://api.novapost.com/developers/index.html#get-/divisions
     *
     * @param array $params
     * @return array Query parameters
     * @throws ClientExceptionInterface
     */
    public function get(array $params = []): array
    {
        return $this->sendRequest('GET', 'divisions', $params);
    }
}
