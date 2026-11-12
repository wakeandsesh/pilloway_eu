<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use NovaDigital\NovaPost\DI\ContainerBuilder;
use NovaDigital\NovaPost\Exception\ApiException;
use NovaDigital\NovaPost\NovaPostApiFactory;
use Psr\Http\Client\ClientExceptionInterface;

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

$apiKey = $_ENV['API_KEY'] ?? '';
$useSandbox = boolval($_ENV['USE_SANDBOX']);

try {
    $guzzleConfig = [
        'http_errors' => true,
        'headers' => [
            'Accept-Language' => 'de',
        ]
    ];
    $containerBuilder = (new ContainerBuilder())
        ->setParameter('config', $guzzleConfig);
    $novaPostApi = (new NovaPostApiFactory())(
        apiKey: $apiKey,
        containerBuilder: $containerBuilder,
        useSandbox: $useSandbox
    );

    $payload = [
        'payerType' => 'Sender',
        'parcels' => [
            [
                'cargoCategory' => 'parcel',
                'insuranceCost' => 600,
                'rowNumber' => 1,
                'width' => '10',
                'length' => '10',
                'height' => '10',
                'actualWeight' => 100
            ],
        ],
        'payerContractNumber' => 'GNPP-00001602',
        'sender' => [
            'companyTin' => '',
            'companyName' => '',
            'countryCode' => 'NL',
            'divisionId' => 1936958
        ],
        'recipient' => [
            'countryCode' => 'UA',
            'divisionId' => 11823
        ]
    ];

    $response = $novaPostApi->shipments()->calculate($payload);
    $cost = 0;
    foreach ($response['services'] as $service) {
        $cost = $cost + $service['cost'];
    }
    echo 'Delivery Cost=' . $cost;
} catch (ApiException $e) {
    echo 'API Error => ' . $e->getMessage() . ' (Code => ' . $e->getCode() . ')\n';
} catch (ClientExceptionInterface $e) {
    echo 'HTTP Client Error => ' . $e->getMessage() . '\n';
} catch (Exception $e) {
    echo 'General Error => ' . $e->getMessage() . '\n';
}