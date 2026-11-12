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
    echo "Creating registry...\n";
    $registry = $novaPostApi->registry()->create([
        'description' => 'Test Registry',
        'shipments' => [
            '383904',
            '383905'
        ]
    ]);
    print_r($registry);

    $registryId = $registry['id'] ?? null;

    if ($registryId) {
        echo "\nAdding shipments to registry...\n";
        $updated = $novaPostApi->registry()->addShipments($registryId, [
            'shipments' => [
                '383906',
                '383907'
            ]
        ]);
        print_r($updated);

        echo "\nRenaming registry...\n";
        $renamed = $novaPostApi->registry()->rename($registryId, [
            'description' => 'Updated Registry Name'
        ]);
        print_r($renamed);

        echo "\nRemoving shipments from registry...\n";
        $removed = $novaPostApi->registry()->removeShipments($registryId, [
            'shipments' => [
                '383906',
                '383907'
            ]
        ]);
        print_r($removed);

        echo "\nDeleting registry...\n";
        $deleted = $novaPostApi->registry()->delete($registryId);
        print_r($deleted);
    }

} catch (\Throwable $e) {
    echo "API Error: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString();
}
