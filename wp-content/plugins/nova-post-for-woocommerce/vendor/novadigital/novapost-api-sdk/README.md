# NovaPost API SDK for PHP

This is the official PHP SDK for the [NovaPost API](https://api.novapost.com/developers/index.html). It provides a convenient and modern way to interact with the NovaPost API, allowing you to easily integrate shipping, tracking, and other services into your PHP application.

The SDK is built with modern best practices, including PSR compliance and a dependency injection container, making it flexible and easy to extend.

## Installation

You can install the SDK via [Composer](https://getcomposer.org/):

```bash
composer require novadigital/novapost-api-sdk
```

## Usage

### Initializing the Client

The easiest way to get started is by using the `NovaDigital\NovaPost\NovaPostApiFactory`. It allows you to configure and create a client instance with just a few lines of code.

```php
use NovaDigital\NovaPost\NovaPostApiFactory;
use NovaDigital\NovaPost\Exception\ApiException;
use NovaDigital\NovaPost\Resources\Division;

// Your API key from your NovaPost account
$apiKey = 'YOUR_API_KEY'; 

try {
    $novaPostApi = (new NovaPostApiFactory())($apiKey);
    $searchParams = [
        'textSearch' => 'berlin',
        'divisionCategories' => [Division::DIVISION_CATEGORY_POSTOMAT]
    ];

    $divisions = $novaPostApi->divisions()->get($searchParams);
} catch (ApiException $e) {
    echo "API Error: " . $e->getMessage();
}
```

### Calculating Shipment Cost

Here's how you can calculate the cost of a shipment:

```php
try {
    $shipmentData = [
        // ... add your shipment calculation data here
    ];

    $calculationResult = $novaPostApi->shipments()->calculate($shipmentData);
    
    // Handle the result...

} catch (ApiException $e) {
    echo "API Error: " . $e->getMessage();
}
```

## Advanced Usage: Overriding Services

The SDK uses a PSR-11 dependency injection container, which gives you complete control over its internal components. You can replace any default service (like the logger or HTTP client) with your own implementation.

This is useful if you want to:
*   Integrate the SDK with your framework's logging system.
*   Customize the HTTP client with special middleware or configurations.
*   Use a different JWT token storage mechanism.

To override a service, you can pass your own instance to the factory:

### Using ContainerBuilder

For more advanced configuration, you can use the `NovaDigital\NovaPost\DI\ContainerBuilder` to customize various aspects of the client:

```php
use NovaDigital\NovaPost\DI\ContainerBuilder;
use NovaDigital\NovaPost\Exception\ApiException;
use NovaDigital\NovaPost\NovaPostApiFactory;
use NovaDigital\NovaPost\Storage\JwtTokenStorageInterface;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Log\LoggerInterface;

use My\Awesome\MyLogger;
use My\Awesome\DbJwtTokenStorageProvider;

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

$apiKey = $_ENV['API_KEY'] ?? '';
$useSandbox = (bool)($_ENV['USE_SANDBOX'] ?? true);

try {
    $containerBuilder = (new ContainerBuilder())
        ->bind(LoggerInterface::class, MyLogger::class)
        ->bind(JwtTokenStorageInterface::class, DbJwtTokenStorageProvider::class);

    $novaPostApi = (new NovaPostApiFactory())(apiKey: $apiKey, containerBuilder: $containerBuilder);

    $payload = [
        //calculation request params
    ];

    $response = $novaPostApi->shipments()->calculate($payload);
} catch (ApiException $e) {
    echo 'API Error => ' . $e->getMessage() . ' (Code => ' . $e->getCode() . ')\n';
}
```

This approach allows you to:
1. Replace any service in the dependency injection container
2. Maintain a clean, fluent interface for configuration
3. Keep your custom service implementations separate from the SDK
4. Test with mock services

### Available Service Overrides

You can override the following services using the `ContainerBuilder`:

- `Psr\Log\LoggerInterface`: For custom logging
- `Psr\Http\Client\ClientInterface`: For custom HTTP client configuration
- `NovaDigital\NovaPost\Storage\JwtTokenStorageInterface`: For custom JWT token storage
- `NovaDigital\NovaPost\Http\ResponseHandlerInterface`: For custom response handling
- `NovaDigital\NovaPost\Http\RetryHandlerInterface`: For custom retry logic

Each service can be overridden using the `bind` method on the `ContainerBuilder`.

## PSR Standards Compliance

This SDK adheres to the following PSR standards, ensuring interoperability and modern, high-quality code:

* **PSR-4: Autoloader**: For autoloading classes.
* **PSR-11: Container Interface**: For a flexible dependency injection container.
* **PSR-3: Logger Interface**: Allowing you to use any compatible logger.
* **PSR-7: HTTP Message Interface**: Used for all API requests and responses.
* **PSR-18: HTTP Client**: For sending HTTP requests.
* **PSR-17: HTTP Factories**: Used internally for creating PSR-7 messages.
* **PSR-12: Extended Coding Standard**: For coding styles.

This commitment to standards makes the SDK reliable, predictable, and easy to integrate into any modern PHP application.
