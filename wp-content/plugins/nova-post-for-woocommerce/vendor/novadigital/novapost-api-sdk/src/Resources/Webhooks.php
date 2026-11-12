<?php

declare(strict_types=1);

namespace NovaDigital\NovaPost\Resources;

use Psr\Http\Client\ClientExceptionInterface;

class Webhooks extends AbstractResource
{
    /**
     * Retrieve a list of all subscriptions (webhooks) associated with the client.
     *
     * @see https://api.novapost.com/developers/index.html#get-/tracking-push/subscribers
     *
     * @param array $params Query parameters
     * @return array
     * @throws ClientExceptionInterface
     */
    public function list(array $params = []): array
    {
        return $this->sendRequest('GET', 'tracking-push/subscribers', $params);
    }

    /**
     * Create a new subscription for receiving tracking notifications via webhooks.
     *
     *  Mandatory parameters:
     *  - type (string) — Type of subscription. Allowed: individual | numbers | legal
     *  - url (string) — The callback URL that should be capable of receiving POST requests with JSON payloads.
     *  - isActive (bool) — Should the subscription be active immediately upon creation.
     *
     *  Optional parameters may include phone, secretToken, eventTypes, sendWarnings, warningEmail, and companyTins
     *
     * @see https://api.novapost.com/developers/index.html#post-/tracking-push/subscribers
     *
     * @param array $params Request parameters
     * @return array
     * @throws ClientExceptionInterface
     */
    public function create(array $params): array
    {
        return $this->sendRequest('POST', 'tracking-push/subscribers', $params);
    }

    /**
     * Update an existing subscription's details.
     *
     *   Mandatory parameters:
     *   - type (string) — Type of subscription. Allowed: individual | numbers | legal
     *   - url (string) — The callback URL that should be capable of receiving POST requests with JSON payloads.
     *   - isActive (bool) — Should the subscription be active immediately upon creation.
     *
     * Optional parameters may include phone, secretToken, eventTypes, sendWarnings, warningEmail, and companyTins
     *
     * @see https://api.novapost.com/developers/index.html#put-/tracking-push/subscribers/-id-
     *
     * @param string $id Subscription ID
     * @param array $params Request parameters
     * @return array
     * @throws ClientExceptionInterface
     */
    public function update(string $id, array $params): array
    {
        return $this->sendRequest('PUT', "tracking-push/subscribers/{$id}", $params);
    }

    /**
     * Delete a subscription by ID.
     *
     * Once deleted, the subscription will no longer receive tracking notifications.
     * This action cannot be undone.
     *
     * @see https://api.novapost.com/developers/index.html#delete-/tracking-push/subscribers/-id-
     *
     * @param string $id Subscription ID
     * @return array
     * @throws ClientExceptionInterface
     */
    public function delete(string $id): array
    {
        return $this->sendRequest('DELETE', "tracking-push/subscribers/{$id}");
    }

    /**
     * Add shipment numbers to an existing subscription.
     *
     * The subscription type must be `numbers` for this operation to be valid.
     *
     * Mandatory parameters:
     * - numbers (array<string>) — List of shipment numbers to add
     *
     * @see https://api.novapost.com/developers/index.html#post-/tracking-push/subscribers/-id-/numbers
     *
     * @param string $id Subscription ID
     * @param array $params Request parameters
     * @return array
     * @throws ClientExceptionInterface
     */
    public function addNumbers(string $id, array $params): array
    {
        return $this->sendRequest('POST', "tracking-push/subscribers/{$id}/numbers", $params);
    }

    /**
     * Check webhook delivery by performing a test webhook call.
     *
     * Requires at least one active subscription.
     *
     * @see https://api.novapost.com/developers/index.html#post-/tracking-push/subscribers/test-webhook
     *
     * @param array $params
     * @return array
     * @throws ClientExceptionInterface
     */
    public function test(array $params = []): array
    {
        return $this->sendRequest('POST', 'tracking-push/subscribers/test-webhook', $params);
    }
}
