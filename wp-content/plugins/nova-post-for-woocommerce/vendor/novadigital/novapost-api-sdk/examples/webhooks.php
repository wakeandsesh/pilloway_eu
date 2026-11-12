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
    echo "Creating webhook subscription...\n";
    $subscription = $novaPostApi->webhooks()->create([
        'url' => 'https://webhook.site/your-unique-id',
        'type' => 'numbers', // or 'numbers', 'legal'
        'isActive' => true,
        'eventTypes' => [] // optional filter
    ]);
    print_r($subscription);

    $subscriptionId = $subscription['id'] ?? null;

    if ($subscriptionId) {
        echo "\nListing subscriptions...\n";
        $list = $novaPostApi->webhooks()->list();
        print_r($list);
        echo "\nUpdating subscription...\n";
        $updated = $novaPostApi->webhooks()->update($subscriptionId, [
            'url' => 'https://webhook.site/c6fdc79b-3326-428a-a753-03d0b79c3714',
            'type' => 'numbers',
            'isActive' => false
        ]);
        print_r($updated);

        echo "\nDeleting subscription...\n";
        $deleted = $novaPostApi->webhooks()->delete($subscriptionId);
        print_r($deleted);
    }

} catch (\Throwable $e) {
    echo "API Error: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString();
}
