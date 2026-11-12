<?php

declare(strict_types=1);

namespace NovaDigital\NovaPost\Resources;

use Psr\Http\Client\ClientExceptionInterface;

class Pickup extends AbstractResource
{
    /**
     * Get a list of courier pickup requests created by the authenticated business client.
     *
     * Optional parameters:
     * - ids | numbers (array<string>) — List of pickup ID(s) or number(s) to filter by
     *
     * @see https://api.novapost.com/developers/index.html#get-/pickups
     *
     * @param array $params Query parameters
     * @return array
     * @throws ClientExceptionInterface
     */
    public function get(array $params = []): array
    {
        return $this->sendRequest('GET', 'pickups', $params);
    }

    /**
     * Create a new courier pickup request.
     *
     * The pickup is initially created in `Draft` status so that shipments can be added.
     * After adding shipments, use the `updateStatus` method
     * to move the pickup to `Created` status for courier processing.
     *
     * To include a specific time interval, retrieve available intervals using the
     * `findTimeIntervals` method and provide the selected interval type in the parameters.
     *
     * @see https://api.novapost.com/developers/index.html#post-/pickups
     *
     * @param array $params Request parameters
     * @return array
     * @throws ClientExceptionInterface
     */
    public function create(array $params): array
    {
        return $this->sendRequest('POST', 'pickups', $params);
    }

    /**
     * Update an existing courier pickup request by ID.
     *
     * Allows modifying address, contact details, time window, and other fields
     *
     * @see https://api.novapost.com/developers/index.html#put-/pickups/-id-
     *
     * @param string $id The ID of the pickup request to update
     * @param array $params Request parameters
     * @return array
     * @throws ClientExceptionInterface
     */
    public function update(string $id, array $params): array
    {
        return $this->sendRequest('PUT', "pickups/{$id}", $params);
    }

    /**
     * Delete an existing courier pickup request by ID.
     *
     * Marks the pickup request as removed in the system and returns the deletion timestamp.
     *
     * @see https://api.novapost.com/developers/index.html#delete-/pickups/-id-
     *
     * @param string $id The ID of the pickup request to delete
     * @return array
     * @throws ClientExceptionInterface
     */
    public function delete(string $id): array
    {
        return $this->sendRequest('DELETE', "pickups/{$id}");
    }

    /**
     * Add shipments to an existing courier pickup request.
     *
     * The pickup request must be in `Draft` status to add shipments.
     *
     *  Mandatory parameters:
     *  - shipments (array<string>) — List of shipment IDs to add
     *
     * @see https://api.novapost.com/developers/index.html#post-/pickups/-id-/shipments
     *
     * @param string $id The ID of the pickup request
     * @param array $params Request parameters
     * @return array
     * @throws ClientExceptionInterface
     */
    public function addShipments(string $id, array $params): array
    {
        return $this->sendRequest('POST', "pickups/{$id}/shipments", $params);
    }

    /**
     * Remove shipments from an existing courier pickup request.
     *
     * The pickup request must be in `Draft` status to modify shipments.
     *
     * Mandatory parameters:
     * - shipments (array<string>) — List of shipment IDs to remove
     *
     * @see https://api.novapost.com/developers/index.html#delete-/pickups/-id-/shipments
     *
     * @param string $id The ID of the pickup request
     * @param array $params Request parameters
     * @return array
     * @throws ClientExceptionInterface
     */
    public function removeShipments(string $id, array $params): array
    {
        return $this->sendRequest('DELETE', "pickups/{$id}/shipments", $params);
    }

    /**
     * Update the status of a pickup request.
     *
     * While in `Draft` status, shipments can be added to the pickup.
     * Once updated to `Created`, the pickup is finalized and ready for courier processing.
     *
     * Mandatory parameters:
     * - lockVersion (int) — Version number for concurrency control to avoid conflicting updates.
     * - status (string) — New status of the pickup request. Allowed: Created
     *
     * Optional parameters:
     * - note (string) — Additional comment or note for this status update.
     *
     * @see https://api.novapost.com/developers/index.html#put-/pickups/-id-/status
     *
     * @param string $id The ID of the pickup request
     * @param array $params Request parameters
     * @return array
     * @throws ClientExceptionInterface
     */
    public function updateStatus(string $id, array $params = []): array
    {
        return $this->sendRequest('PUT', "pickups/{$id}/status", $params);
    }

    /**
     * Find available time intervals for pickups based on the pickup location and type.
     *
     * Mandatory parameters:
     * - countryCode (string) — ISO country code where pickup is requested (e.g., 'PL')
     * - type (string) — Type of time interval. Allowed: PickupDayToDay | PickupNextDay
     *
     * Optional parameters may include addressParts, longitude, latitude, divisionId, and weight.
     *
     * @see https://api.novapost.com/developers/index.html#post-/time-intervals/find
     *
     * @param array $params Request parameters
     * @return array
     * @throws ClientExceptionInterface
     */
    public function findTimeIntervals(array $params = []): array
    {
        return $this->sendRequest('POST', 'time-intervals/find', $params);
    }
}
