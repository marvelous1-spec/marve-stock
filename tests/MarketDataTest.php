<?php
declare(strict_types=1);

spl_autoload_register(static function (string $class): void {
    if (!str_starts_with($class, 'App\\')) {
        return;
    }

    $path = dirname(__DIR__) . '/app/' . str_replace('\\', '/', substr($class, 4)) . '.php';
    if (is_file($path)) {
        require $path;
    }
});

use App\Market\NgnMarketDataProvider;
use App\Broker\BrokerExecutionService;

function expect(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$provider = new NgnMarketDataProvider(static fn (string $baseUrl, string $apiKey): array => [
    'success' => true,
    'data' => ['data' => [[
        'symbol' => 'TEST',
        'name' => 'Recorded Provider Fixture Plc',
        'price' => '123.45',
        'price_change' => '-1.20',
        'price_change_percent' => '-0.96',
        'last_updated' => '2026-09-11T10:00:00+01:00',
    ]]],
]);

$overview = $provider->overview();
expect($overview['status'] !== 'UNAVAILABLE', 'A valid provider payload must not be unavailable.');
expect(count($overview['items']) === 1, 'The provider must normalize one valid quote.');
expect($overview['items'][0]['symbol'] === 'TEST', 'The provider must preserve the symbol.');
expect($overview['items'][0]['last_price'] === '123.45', 'The provider must preserve the supplied price.');

$badPayloadProvider = new NgnMarketDataProvider(static fn (string $baseUrl, string $apiKey): array => ['success' => true, 'data' => []]);
$badOverview = $badPayloadProvider->overview();
expect($badOverview['status'] === 'UNAVAILABLE', 'An invalid provider payload must be rejected safely.');

$broker = new BrokerExecutionService();
expect($broker->status()['status'] === 'NOT_CONNECTED', 'Broker execution must remain disconnected without an approved adapter.');

echo "MarketDataTest passed\n";
