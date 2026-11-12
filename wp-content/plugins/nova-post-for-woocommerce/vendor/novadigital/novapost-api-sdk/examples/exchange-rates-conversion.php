<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use NovaDigital\NovaPost\Exception\ApiException;
use Psr\Http\Client\ClientExceptionInterface;
use NovaDigital\NovaPost\NovaPostApiFactory;

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

$apiKey = $_ENV['API_KEY'] ?? '';
$useSandbox = (bool)($_ENV['USE_SANDBOX'] ?? true);

try {
    $novaPostApi = (new NovaPostApiFactory())(apiKey: $apiKey, useSandbox: $useSandbox);

    $payload = [
        'amount' => 100.00,
        'countryCode' => 'UA',
        'currencyCode' => 'USD',
        'date' => (new DateTime('today'))->format('Y-m-d\TH:i:s.u\Z'),
    ];

    $response = $novaPostApi->exchangeRates()->convert($payload);

    echo "Success: Exchange rates retrieved\n";

    // Actual response sample:
    $responseExample = [
        'requestCurrency' => [
            'currencyCode' => 'USD',
            'amount' => 100,
        ],
        'mainCurrency' => [
            'currencyCode' => 'UAH',
            'amount' => 3662.16,
        ],
        'convertedCurrencies' => [
            ['currencyCode' => 'TND', 'amount' => 317.18],
            ['currencyCode' => 'CZK', 'amount' => 2320.03],
            ['currencyCode' => 'BRL', 'amount' => 504.08],
            ['currencyCode' => 'TRY', 'amount' => 2755.78],
            ['currencyCode' => 'RON', 'amount' => 472.25],
            ['currencyCode' => 'EUR', 'amount' => 95.02],
            ['currencyCode' => 'MDL', 'amount' => 1823.15],
            ['currencyCode' => 'ZAR', 'amount' => 1956.6],
            ['currencyCode' => 'NZD', 'amount' => 168.27],
            ['currencyCode' => 'SAR', 'amount' => 375.59],
            ['currencyCode' => 'LYD', 'amount' => 489.62],
            ['currencyCode' => 'PLN', 'amount' => 437.49],
            ['currencyCode' => 'GBP', 'amount' => 82.31],
        ],
    ];

    // loop through exchange rates and find the one for EUR
    $eurRate = $response['convertedCurrencies'][array_search(
        'EUR',
        array_column($response['convertedCurrencies'], 'currencyCode')
    )];

    echo "I have {$eurRate['amount']} Euros!\n";
} catch (ApiException $e) {
    echo "API Error: " . $e->getMessage() . " (Code: " . $e->getCode() . ")\n";
} catch (ClientExceptionInterface $e) {
    echo "HTTP Client Error: " . $e->getMessage() . "\n";
} catch (Exception $e) {
    echo "General Error: " . $e->getMessage() . "\n";
}
