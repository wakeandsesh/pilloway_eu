<?php

require_once __DIR__ . '/../vendor/autoload.php';

use NovaDigital\NovaPost\NovaPostApiFactory;
use NovaDigital\NovaPost\Exception\ApiException;

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

$apiKey = $_ENV['API_KEY'] ?? '';
$useSandbox = (bool)($_ENV['USE_SANDBOX'] ?? true);

$factory = new NovaPostApiFactory();
$novaPostApi = $factory(
    apiKey: $apiKey,
    useSandbox: $useSandbox
);

try {
    // Example: Get customs fees settings for different countries
    $countries = ['PL', 'DE', 'FR', 'IT', 'ES'];

    echo "Checking customs fees settings for various countries:\n\n";

    foreach ($countries as $countryCode) {
        echo "Country: {$countryCode}\n";
        
        try {
            $customsSettings = $novaPostApi->dictionary()->customsFees($countryCode);
            
            $canPayCustoms = $customsSettings['customFeeActive'] ? 'Yes' : 'No';
            $maxValue = $customsSettings['declaredCost'] ?? 'N/A';
            
            echo "  - Sender can pay customs duties: {$canPayCustoms}\n";
            echo "  - Maximum declared value: {$maxValue}\n";
            
            if ($customsSettings['customFeeActive']) {
                echo "  ℹ️  Sender from Ukraine can pay customs duties for parcels up to {$maxValue} in local currency\n";
            } else {
                echo "  ⚠️  Sender cannot pay customs duties. Recipient must pay.\n";
            }
            
        } catch (ApiException $e) {
            echo "  ❌ Error: " . $e->getMessage() . "\n";
        }
        
        echo "\n";
    }

    // Practical example: Determine payer for customs fees
    echo "=== Practical Example ===\n";
    echo "Shipment from Ukraine to Poland with declared value of 150 EUR\n\n";
    
    $polandSettings = $novaPostApi->dictionary()->customsFees('PL');
    $declaredValue = 150;
    $maxAllowed = $polandSettings['declaredCost'];
    
    if ($polandSettings['customFeeActive'] && $declaredValue <= $maxAllowed) {
        echo "✅ Sender can pay customs duties (value {$declaredValue} EUR ≤ limit {$maxAllowed} EUR)\n";
        echo "   Set: payerFeesCustoms = 'Sender'\n";
    } else {
        echo "❌ Sender cannot pay customs duties (value {$declaredValue} EUR > limit {$maxAllowed} EUR)\n";
        echo "   Set: payerFeesCustoms = 'Recipient'\n";
    }

} catch (\Throwable $e) {
    echo "API Error: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString();
}
