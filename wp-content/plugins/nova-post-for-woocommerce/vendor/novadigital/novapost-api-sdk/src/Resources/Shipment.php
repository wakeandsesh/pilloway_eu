<?php

declare(strict_types=1);

namespace NovaDigital\NovaPost\Resources;

use Psr\Http\Client\ClientExceptionInterface;

class Shipment extends AbstractResource
{
    /**
     * Create a transportation document (shipment).
     *
     * Required parameters include sender, recipient, and parcels data.
     * Optional fields for customs authorities are supported for cross-border shipments.
     *
     * @see https://api.novapost.com/developers/index.html#post-/shipments
     *
     * @param array $params Request parameters
     * @return array
     * @throws ClientExceptionInterface
     */
    public function create(array $params): array
    {
        return $this->sendRequest('POST', 'shipments', $params);
    }

    /**
     * Retrieve transportation documents (shipments) created by the authenticated client.
     *
     * Optional parameters:
     * - numbers (array<string>) — Filter by transportation document number(s).
     *
     * @see https://api.novapost.com/developers/index.html#get-/shipments
     *
     * @param array $params Query parameters
     * @return array
     * @throws ClientExceptionInterface
     */
    public function get(array $params = []): array
    {
        return $this->sendRequest('GET', 'shipments', $params);
    }

    /**
     * Update an existing transportation document (shipment) by ID.
     *
     * Required parameters include sender, recipient, and parcels data.
     * Optional fields for customs authorities are supported for cross-border shipments.
     *
     * @see https://api.novapost.com/developers/index.html#put-/shipments/-id-
     *
     * @param string $id The ID of the shipment to update
     * @param array $params Request parameters
     * @return array
     * @throws ClientExceptionInterface
     */
    public function update(string $id, array $params): array
    {
        return $this->sendRequest('PUT', "shipments/{$id}", $params);
    }

    /**
     * Delete an existing transportation document (shipment) by ID.
     *
     * Regional behavior:
     * - Europe: deletion by Ref ID is primary; deletion by shipment number (e.g., SHPL0123456789) is also supported.
     * - Ukraine: deletion is supported only by Ref ID; deletion by shipment number is not supported.
     *
     * @see https://api.novapost.com/developers/index.html#delete-/shipments/-id-
     *
     * @param string $idOrNumber The shipment Ref ID or (in Europe) the shipment number
     * @return array
     * @throws ClientExceptionInterface
     */
    public function delete(string $idOrNumber): array
    {
        return $this->sendRequest('DELETE', "shipments/{$idOrNumber}");
    }

    /**
     * Calculate the estimated delivery cost for a shipment.
     *
     * Required parameters include sender, recipient, parcels data, and payer type.
     *
     * Parameter notes:
     *  - payerType (string) — Identifies who is responsible for payment. Allowed: Sender | Recipient | ThirdPerson.
     *  - payerContractNumber (string) — Required when payerType is ThirdPerson for shipments within Europe
     *    or from Europe to Ukraine (e.g., 'CNPP-00001797'). Not required for shipments from Ukraine.
     *
     * @see https://api.novapost.com/developers/index.html#post-/shipments/calculations
     *
     * @param array $params Request parameters
     * @return array
     * @throws ClientExceptionInterface
     */
    public function calculate(array $params): array
    {
        return $this->sendRequest('POST', 'shipments/calculations', $params);
    }

    /**
     * Retrieve a transportation document marking (PDF) by shipment number(s).
     *
     * Generates a printable PDF document that can be attached or affixed to cargo.
     *
     * Mandatory parameters:
     * - numbers (array<string>) — Shipment numbers. When the type `marking` is used, only one number is allowed.
     * - type (string) — Document type to print. Allowed: marking | international | invoice
     *   • marking — shipment label 100x100 (local or international)
     *   • international — customs declaration/invoice
     *   • invoice — commercial invoice
     *
     * Optional parameters:
     * - printSizeType (string) — Output size. Allowed: size_100_100 | size_A4
     *   • For type `marking`, only `size_100_100` is supported.
     * - deliveryType (string) — Applicable only for type `marking`. Allowed: Pickup | Shipment
     *   • Pickup — first mile
     *   • Shipment — last mile
     *
     * @see https://api.novapost.com/developers/index.html#get-/shipments/print
     *
     * @param array $params Query parameters
     * @return array|string Binary PDF content on success or validation error payload
     * @throws ClientExceptionInterface
     */
    public function print(array $params): array|string
    {
        return $this->sendRequest('GET', 'shipments/print', $params);
    }

    /**
     * Upload accompanying document files for a shipment.
     *
     * Attaches supporting documents (e.g., invoice, product specification, customs declaration)
     * to a specified shipment, improving processing, clearance, and traceability.
     *
     * Mandatory parameters:
     * - file (string) — Base64-encoded file content
     *
     * Optional parameters:
     * - fileName (string) — Original file name (e.g., 'invoice.pdf')
     * - fileContentType (string) — MIME type. Allowed: application/pdf
     *
     * @see https://api.novapost.com/developers/index.html#post-/shipments/uploads/-id-
     *
     * @param string $id The shipment Ref ID to which the document will be attached
     * @param array $params Request parameters
     * @return array
     * @throws ClientExceptionInterface
     */
    public function attachDocument(string $id, array $params): array
    {
        return $this->sendRequest('POST', "shipments/uploads/{$id}", $params);
    }

    /**
     * List available attachment files (photos and/or signature) for a shipment.
     *
     * Returns a list of attachment files for the specified shipment number.
     *
     * @see https://api.novapost.com/developers/index.html#get-/shipments/-shipmentNumber-/attachments
     *
     * @param string $number Shipment number (e.g., 'SHDE2072834273')
     * @return array
     * @throws ClientExceptionInterface
     */
    public function listAttachments(string $number): array
    {
        return $this->sendRequest('GET', "shipments/{$number}/attachments");
    }

    /**
     * Download a specific attachment file for a shipment.
     *
     * Returns the binary contents of the file identified by fileId.
     *
     * @see https://api.novapost.com/developers/index.html#get-/shipments/-shipmentNumber-/attachments/-fileId-
     *
     * @param string $number Shipment number (e.g., 'SHDE2072834273')
     * @param string $fileId Unique file identifier (e.g., '60946ff4-8170-415b-ab66-719848ef1da7')
     * @return array|string Binary file content on success or validation/error payload
     * @throws ClientExceptionInterface
     */
    public function downloadAttachment(string $number, string $fileId): array|string
    {
        return $this->sendRequest('GET', "shipments/{$number}/attachments/{$fileId}");
    }

    /**
     * Retrieve full tracking information for shipment(s).
     *
     * Returns current status, tracking history, actual tracking history, shipment description,
     * and extended information about related shipments. Additional data blocks can be included
     * via optional parameters.
     *
     * Mandatory parameters:
     * - numbers | ids (array<string>) — Shipment number(s) or ID(s)
     * - trackingDateFrom (string, ISO 8601 date-time) — Required when none of ids/numbers are present.
     * - trackingDateTo (string, ISO 8601 date-time) — Required when none of ids/numbers are present.
     *
     * Optional parameters:
     * - withUndeliveryReason (bool) — Include reasons for non-delivery. Default: false.
     * - withCreatedOnTheBasis (bool) — Include related shipments (Redirecting, Return, Utilization, Redelivery).
     * Default: false.
     * - countryCode (string) — Two-letter ISO sender country code to filter tracking history by country.
     * - external (bool) — Include an array of related shipments with extended info (owner name, number,
     *   creation date). Default: false.
     *
     * @see https://api.novapost.com/developers/index.html#get-/shipments/tracking
     *
     * @param array $params Query parameters
     * @return array
     * @throws ClientExceptionInterface
     */
    public function tracking(array $params): array
    {
        return $this->sendRequest('GET', 'shipments/tracking', $params);
    }

    /**
     * Retrieve basic tracking information (history) for shipment(s).
     *
     * Enables fetching the current status and tracking history by providing
     * transportation document number(s) or ID(s).
     *
     * Mandatory parameters:
     * - numbers | ids (array<string>) — Shipment number(s) or ID(s)
     * - trackingDateFrom (string, ISO 8601 date-time) — Required when none of ids/numbers are present.
     * - trackingDateTo (string, ISO 8601 date-time) — Required when none of ids/numbers are present.
     *
     * Optional parameters:
     * - extended (bool) — Include related shipments in the response. Default: false.
     *
     * @see https://api.novapost.com/developers/index.html#get-/shipments/tracking/history
     *
     * @param array $params Query parameters
     * @return array
     * @throws ClientExceptionInterface
     */
    public function trackingHistory(array $params): array
    {
        return $this->sendRequest('GET', 'shipments/tracking/history', $params);
    }

    /**
     * Create a Light Return shipment after the original order has been delivered.
     *
     * This method allows customers to create a return shipment after delivery,
     * regardless of who handled the last mile (Nova Post or a partner).
     *
     * Requirements:
     * - The parent shipment must have **Delivered** status
     * - The parent shipment must include the **AllowedLightReturn** service
     * - The return must be created within the allowed return period
     * - No Light Return has already been created for the same parent shipment
     *
     * Mandatory parameters:
     * - number (string) — The parent shipment number for which the return is initiated
     *
     * Optional parameters:
     * - divisionId (string) — Division that will process the return
     * - senderPhone (string) — Phone number of the sender for the return shipment
     * - addressParts (object) — Address structure for pickup or delivery
     * - invoice (object) — Customs data for cross-border returns
     * - parcels (array) — Parcel details
     *
     * Note: If optional parameters are not specified, their values are automatically
     * inherited from the parent shipment.
     *
     * @see https://api.novapost.com/developers/index.html#post-/shipments/light-return
     *
     * @param array $params Request parameters
     * @return array
     * @throws ClientExceptionInterface
     */
    public function createLightReturn(array $params): array
    {
        return $this->sendRequest('POST', 'shipments/light-return', $params);
    }

    /**
     * Get verification status of international shipments (UA→World direction).
     *
     * Returns verification statuses for international shipments from Ukraine to World.
     * Responses and errors from the core system are proxied unchanged.
     *
     * Mandatory parameters:
     * - refs (array<string>) — Array of shipment references (must not be empty)
     * - state (string) — Verification state filter. Allowed: Order | Closed | allOrders
     *
     * @see https://api.novapost.com/developers/index.html#get-/shipments/international/status
     *
     * @param array $params Query parameters
     * @return array
     * @throws ClientExceptionInterface
     */
    public function getInternationalStatus(array $params): array
    {
        return $this->sendRequest('GET', 'shipments/international/status', $params);
    }

    /**
     * Upload supporting documents to a specific shipment by its ID.
     *
     * This is an alternative method for attaching invoices, product specifications,
     * customs declarations, or other shipment-related documents.
     *
     * File naming:
     * - If "fileName" parameter is provided, the uploaded file will be stored with that name
     * - If "fileName" is not provided, the default file name will be set to "invoice"
     *
     * Mandatory parameters:
     * - file (string) — Base64-encoded document file (PDF, JPEG, or other supported formats)
     *
     * Optional parameters:
     * - fileName (string) — File name (defaults to "invoice" if not provided)
     *
     * @see https://api.novapost.com/developers/index.html#post-/shipments/uploads/-id-
     *
     * @param string $id Unique identifier of the shipment to which the files are attached
     * @param array $params Request parameters
     * @return array
     * @throws ClientExceptionInterface
     */
    public function uploadDocument(string $id, array $params): array
    {
        return $this->sendRequest('POST', "shipments/uploads/{$id}", $params);
    }
}
