<?php

declare(strict_types=1);

namespace NovaDigital\NovaPost\Resources;

use Psr\Http\Client\ClientExceptionInterface;

class Registry extends AbstractResource
{
    /**
     * Create a shipment registry that combines multiple shipments into a single document.
     *
     * This method is available for shipments from Ukraine to the World.
     *
     * Limitations:
     * - Creating registers is available only for shipments originating from Ukraine
     * - A shipment can be added to a register only if the Sender's data (city, counterparty, address)
     *   are identical for all shipments being added
     * - A shipment can be added only to one register — the same document cannot be added to multiple
     *   registers simultaneously
     * - A shipment cannot be added to a register if a printed form has already been generated for this
     *   document and the shipment date (printing date) is earlier than yesterday relative to the
     *   register creation date
     * - A shipment can be added to a register only until an express waybill has been created based on it
     *   (or until the shipment has been scanned at a Nova Poshta branch/unit)
     * - A shipment marked for deletion cannot be added to a register
     * - After the register's printed form has been generated, adding shipments to it is blocked
     *
     * Mandatory parameters:
     * - description (string) — Register name
     * - shipments (array<string>) — Array of unique shipment identifiers (IDs)
     *
     * Example:
     * [
     *   'description' => 'My Registry',
     *   'shipments' => [
     *     '42cd5e04-986d-11f0-903f-005056bd9e02',
     *     '5bd6a533-986d-11f0-903f-005056bd9e02'
     *   ]
     * ]
     *
     * @see https://api.novapost.com/developers/index.html#post-/registry
     *
     * @param array $params Request parameters
     * @return array
     * @throws ClientExceptionInterface
     */
    public function create(array $params): array
    {
        return $this->sendRequest('POST', 'registry', $params);
    }

    /**
     * Add already created shipments to an existing registry.
     *
     * This method is available for shipments from Ukraine to the World.
     *
     * Restrictions:
     * - A shipment can be added to a registry only if the Sender's data (city, counterparty, address)
     *   are identical to the shipments already included in the registry
     * - A shipment can be added only to one register - the same document cannot be added to multiple
     *   registers simultaneously
     * - A shipment cannot be added to a register if a printed form has already been generated for this
     *   document and the shipment date (printing date) is earlier than yesterday relative to the
     *   register creation date
     * - A shipment can be added to a register only until an express waybill has been created based on it
     *   (or until the shipment has been scanned at a Nova Poshta branch/unit)
     * - A shipment marked for deletion cannot be added to a register
     * - After the register's printed form has been generated, adding shipments to it is blocked
     *
     * Mandatory parameters:
     * - shipments (array<string>) — Array of unique shipment identifiers (IDs) to be added
     *
     * Example:
     * [
     *   'shipments' => [
     *     'd818d8ca-9a00-11f0-903f-005056bd9e02'
     *   ]
     * ]
     *
     * @see https://api.novapost.com/developers/index.html#post-/registry/-id-/shipments
     *
     * @param string $id Unique register identifier to which the shipment will be added
     * @param array $params Request parameters
     * @return array
     * @throws ClientExceptionInterface
     */
    public function addShipments(string $id, array $params): array
    {
        return $this->sendRequest('POST', "registry/{$id}/shipments", $params);
    }

    /**
     * Remove specific shipments from an existing registry.
     *
     * Mandatory parameters:
     * - shipments (array<string>) — Array of unique shipment identifiers (IDs) to be removed
     *
     * Example:
     * [
     *   'shipments' => [
     *     'd818d8ca-9a00-11f0-903f-005056bd9e02'
     *   ]
     * ]
     *
     * @see https://api.novapost.com/developers/index.html#delete-/registry/-id-/shipments
     *
     * @param string $id Unique register identifier from which the shipment must be removed
     * @param array $params Request parameters
     * @return array
     * @throws ClientExceptionInterface
     */
    public function removeShipments(string $id, array $params): array
    {
        return $this->sendRequest('DELETE', "registry/{$id}/shipments", $params);
    }

    /**
     * Rename an existing register.
     *
     * Mandatory parameters:
     * - description (string) — New registry name
     *
     * @see https://api.novapost.com/developers/index.html#put-/registry/-id-/rename
     *
     * @param string $id Unique register identifier to be renamed
     * @param array $params Request parameters
     * @return array
     * @throws ClientExceptionInterface
     */
    public function rename(string $id, array $params): array
    {
        return $this->sendRequest('PUT', "registry/{$id}/rename", $params);
    }

    /**
     * Permanently delete an existing register.
     *
     * @see https://api.novapost.com/developers/index.html#delete-/registry/-id-
     *
     * @param string $id Unique register identifier to be deleted
     * @return array
     * @throws ClientExceptionInterface
     */
    public function delete(string $id): array
    {
        return $this->sendRequest('DELETE', "registry/{$id}");
    }
}
