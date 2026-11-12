<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use NovaDigital\NovaPost\Exception\ApiException;
use NovaDigital\NovaPost\NovaPostApiFactory;
use Psr\Http\Client\ClientExceptionInterface;

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

$apiKey = $_ENV['API_KEY'] ?? '';
$useSandbox = (bool)($_ENV['USE_SANDBOX'] ?? true);

try {
    $novaPostApi = (new NovaPostApiFactory())(apiKey: $apiKey, useSandbox: $useSandbox);

    $payload = [
        'status' => 'ReadyToShip',
        'clientOrder' => '35',
        'note' => '',
        'payerType' => 'Sender',
        'payerContractNumber' => null,
        'invoice' => null,
        'services' => [],
        'parcels' => [[
            'cargoCategory' => 'parcel',
            'parcelDescription' => 'Product B, Product A - S',
            'insuranceCost' => 400.00,
            'rowNumber' => 1,
            'width' => 300,
            'length' => 300,
            'height' => 300,
            'actualWeight' => 1200.00,
        ]],
        'sender' => [
            'companyTin' => '',
            'companyName' => '',
            'phone' => '+48211111111',
            'email' => 'jan.kowalski@test.pl',
            'name' => 'Jan Kowalski',
            'countryCode' => 'PL',
            'ioss' => null,
            'addressParts' => [
                'postCode' => '00-345',
                'region' => 'PL',
                'city' => 'Warszawa',
                'street' => 'Drewniana',
                'building' => '8',
                'block' => '',
                'flat' => '',
                'note' => '',
            ],
        ],
        'recipient' => [
            'companyTin' => '',
            'companyName' => '',
            'phone' => '+48222222222',
            'email' => 'anna.nowak@test.pl',
            'name' => 'Anna Nowak',
            'countryCode' => 'PL',
            'ioss' => null,
            'addressParts' => [
                'street' => 'Tamka 3',
                'building' => '-',
                'city' => 'Warszawa',
                'region' => '',
                'postCode' => '00-349',
                'flat' => '',
                'block' => '',
                'note' => ''
            ],
        ],
    ];

    $response = $novaPostApi->shipments()->create($payload);
    echo 'Shipment created successfully, number=' . $response['number'] . PHP_EOL;
} catch (ApiException $e) {
    echo 'API Error => ' . $e->getMessage() . ' (Code => ' . $e->getCode() . ')\n';
} catch (ClientExceptionInterface $e) {
    echo 'HTTP Client Error => ' . $e->getMessage() . '\n';
} catch (Exception $e) {
    echo 'General Error => ' . $e->getMessage() . '\n';
}
