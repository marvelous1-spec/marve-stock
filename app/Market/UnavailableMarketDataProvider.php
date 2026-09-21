<?php
declare(strict_types=1);
namespace App\Market;

final class UnavailableMarketDataProvider implements MarketDataProviderInterface
{
    public function overview(): array { return ['status' => 'UNAVAILABLE', 'as_of' => null, 'source' => null, 'items' => []]; }
    public function instrument(string $symbol): ?array { return null; }
}
