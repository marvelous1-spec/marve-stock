<?php
declare(strict_types=1);
namespace App\Market;

use App\Config\Env;
use App\Core\Database;

final class MarketDataService
{
    private MarketDataProviderInterface $provider;

    public function __construct(?MarketDataProviderInterface $provider = null)
    {
        $this->provider = $provider ?? $this->configuredProvider();
    }

    /** @return array<string,mixed> */
    public function overview(): array
    {
        $result = $this->provider->overview();
        if ($result['status'] !== 'UNAVAILABLE' && $result['items'] !== []) {
            $result['items'] = $this->withLogos($result['items']);
            $this->cacheQuotes($result);
            return $result;
        }

        if ($result['status'] === 'UNAVAILABLE') {
            try {
                $q = Database::connection()->query("SELECT q.symbol, q.company_name, q.last_price, q.change_amount, q.change_percent, q.observed_at, q.data_status, a.logo_url FROM market_quotes q LEFT JOIN assets a ON a.id = q.asset_id WHERE q.observed_at > DATE_SUB(NOW(), INTERVAL 1 DAY) ORDER BY q.observed_at DESC LIMIT 100");
                $cached = $q->fetchAll();
                if ($cached) {
                    return ['status' => 'STALE', 'as_of' => $cached[0]['observed_at'], 'source' => 'cached', 'items' => $cached, 'notice' => 'Market data temporarily unavailable. Prices shown are stale.'];
                }
            } catch (\Throwable $exception) {
                error_log('Market quote cache is unavailable: ' . $exception->getMessage());
                return $result + ['notice' => 'Market data is unavailable because the market-data database is not ready.'];
            }
        }

        return $result + ['notice' => 'Market data temporarily unavailable.'];
    }

    /** @return array<string,mixed> */
    public function marketStatus(): array
    {
        if ($this->provider instanceof NgnMarketDataProvider) return $this->provider->marketStatus();
        return ['status' => 'UNAVAILABLE', 'is_open' => null, 'closes_at' => null, 'next_opens_at' => null, 'source' => null, 'notice' => 'Market session status is unavailable because the provider is not configured.'];
    }

    private function configuredProvider(): MarketDataProviderInterface
    {
        return strtolower((string) Env::get('MARKET_DATA_PROVIDER', 'none')) === 'ngnmarket'
            ? new NgnMarketDataProvider()
            : new UnavailableMarketDataProvider();
    }

    /** @param array<int,array<string,mixed>> $items @return array<int,array<string,mixed>> */
    private function withLogos(array $items): array
    {
        try {
            $symbols = array_values(array_filter(array_map(static fn (array $item): string => (string)($item['symbol'] ?? ''), $items)));
            if (!$symbols) return $items;
            $placeholders = implode(',', array_fill(0, count($symbols), '?'));
            $statement = Database::connection()->prepare("SELECT symbol, logo_url FROM assets WHERE symbol IN ($placeholders)");
            $statement->execute($symbols);
            $logos = [];
            foreach ($statement->fetchAll() as $asset) $logos[$asset['symbol']] = $asset['logo_url'];
            foreach ($items as &$item) $item['logo_url'] = $logos[$item['symbol']] ?? null;
            unset($item);
        } catch (\Throwable $exception) {
            error_log('Asset logos are unavailable: ' . $exception->getMessage());
        }
        return $items;
    }

    /** @param array<string,mixed> $result */
    private function cacheQuotes(array $result): void
    {
        try {
            $db = Database::connection();
            $insert = $db->prepare('INSERT INTO market_quotes (symbol, company_name, last_price, change_amount, change_percent, observed_at, data_status, source) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');

            $db->beginTransaction();
            foreach ($result['items'] as $item) {
                $observedAt = $this->observedAt($item['observed_at'] ?? null);
                $insert->execute([
                    $item['symbol'],
                    $item['company_name'] ?? null,
                    $item['last_price'],
                    $item['change_amount'] ?? null,
                    $item['change_percent'] ?? null,
                    $observedAt,
                    $item['data_status'],
                    $result['source'],
                ]);
            }
            $db->exec('DELETE FROM market_quotes WHERE observed_at < DATE_SUB(NOW(), INTERVAL 90 DAY)');
            $db->commit();
        } catch (\Throwable $exception) {
            if (isset($db) && $db->inTransaction()) {
                $db->rollBack();
            }
            error_log('Unable to cache market quotes: ' . $exception->getMessage());
        }
    }

    private function observedAt(mixed $value): string
    {
        if (is_string($value) && $value !== '') {
            try {
                return (new \DateTimeImmutable($value))->format('Y-m-d H:i:s');
            } catch (\Exception) {
                // Use the server receipt time only when the provider timestamp is malformed.
            }
        }

        return date('Y-m-d H:i:s');
    }
}
