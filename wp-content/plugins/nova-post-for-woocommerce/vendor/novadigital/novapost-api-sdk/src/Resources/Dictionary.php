<?php

declare(strict_types=1);

namespace NovaDigital\NovaPost\Resources;

use Psr\Http\Client\ClientExceptionInterface;

class Dictionary extends AbstractResource
{
    /**
     * Retrieve a list of measurement units.
     *
     * Returns measurement units (e.g., pieces, meters, kilograms)
     * that can be used to describe items using the metric system.
     *
     * @see https://api.novapost.com/developers/index.html#get-/dictionary/measurements
     *
     * @param array $params Query parameters
     * @return array
     * @throws ClientExceptionInterface
     */
    public function measurements(array $params = []): array
    {
        return $this->sendRequest('GET', 'dictionary/measurements', $params);
    }

    /**
     * Retrieve a list of available currencies.
     *
     * Returns currencies that can be used when creating transportation documents (shipments).
     *
     * Optional parameters:
     * - codes (array<string>) — Filter by currency codes. Example: ['UAH', 'EUR']
     *
     * @see https://api.novapost.com/developers/index.html#get-/dictionary/currencies
     *
     * @param array $params Query parameters
     * @return array
     * @throws ClientExceptionInterface
     */
    public function currencies(array $params = []): array
    {
        return $this->sendRequest('GET', 'dictionary/currencies', $params);
    }

    /**
     * Retrieve a list of cargo classifiers (UKT ZED).
     *
     * Returns predefined cargo classification entries (e.g., ID, name, description, category, examples)
     * used to categorize goods when creating transportation documents (shipments).
     *
     * Note:
     * - UKT ZED/HsCode format depends on the destination country:
     *   • UA — typically 8-digit codes
     *   • CA, MD — strictly 10-digit codes (any non-numeric characters in HsCode are removed before validation)
     *
     * Mandatory parameters:
     * - country-code (string) — ISO code of the recipient country (e.g., 'MD')
     * - keyword (string) — Search keyword for the classifier
     *
     * Optional parameters:
     * - fuzzy (bool) — Enable approximate matching
     * - locale (string) — Language code (ISO 639-1)(e.g., 'uk')
     * - size (int) — Number of matching classifiers to return
     *
     * @see https://api.novapost.com/developers/index.html#get-/dictionary/classifier
     *
     * @param array $params Query parameters
     * @return array
     * @throws ClientExceptionInterface
     */
    public function cargoClassifiers(array $params): array
    {
        return $this->sendRequest('GET', 'dictionary/classifier', $params);
    }

    /**
     * Get customs-fees settings for a specific destination country.
     *
     * This method provides country-specific rules that determine whether a sender from Ukraine
     * is allowed to pay customs duties and the maximum declared parcel value for which this
     * option is permitted.
     *
     * If the returned `declaredCost` limit is exceeded, the sender cannot act as the customs-fee payer.
     * In such cases, the `payerFeesCustoms` parameter must be set to `Recipient`, as only the
     * recipient is allowed to pay the customs duties for parcels exceeding this value.
     *
     * Response fields:
     * - customFeeActive (bool) — Indicates if sender can pay customs duties (true/false)
     * - declaredCost (number) — Maximum parcel value (in recipient country's currency)
     *   for which the sender may pay customs duties
     *
     * @see https://api.novapost.com/developers/index.html#get-/dictionary/customs-fees/-code-
     *
     * @param string $countryCode ISO 3166-1 Alpha-2 country code (e.g., 'PL', 'DE', 'FR')
     * @return array
     * @throws ClientExceptionInterface
     */
    public function customsFees(string $countryCode): array
    {
        return $this->sendRequest('GET', "dictionary/customs-fees/{$countryCode}");
    }
}
