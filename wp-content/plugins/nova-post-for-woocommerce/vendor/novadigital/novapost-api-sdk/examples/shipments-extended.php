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
    // Example 1: Create Light Return Shipment
    echo "Creating Light Return shipment...\n";
    $lightReturn = $novaPostApi->shipments()->createLightReturn([
        'number' => 'SHMD0000000000', // Parent shipment number
        // Optional parameters can be added here
        // 'divisionId' => '1835903',
        // 'senderPhone' => '490000000000',
    ]);
    print_r($lightReturn);

    // Example 2: Get International Status (UA→World)
    echo "\nGetting international shipment status...\n";
    $internationalStatus = $novaPostApi->shipments()->getInternationalStatus([
        'refs' => [
            '38e4fe97-9484-11f0-903f-005056bd9e02',
            '42cd5e04-986d-11f0-903f-005056bd9e02'
        ],
        'state' => 'allOrders' // Order | Closed | allOrders
    ]);
    print_r($internationalStatus);

    // Example 3: Upload Document (alternative method)
    echo "\nUploading document to shipment...\n";
    
    // Read a sample PDF file and encode it to base64
    $filePath = __DIR__ . '/sample-invoice.pdf';
    if (file_exists($filePath)) {
        $fileContent = file_get_contents($filePath);
        $base64Content = base64_encode($fileContent);
        
        $uploadResult = $novaPostApi->shipments()->uploadDocument('980911', [
            'file' => $base64Content,
            'fileName' => 'invoice.pdf'
        ]);
        print_r($uploadResult);
    } else {
        echo "Sample file not found. Skipping upload example.\n";
    }

} catch (\Throwable $e) {
    echo "API Error: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString();
}
