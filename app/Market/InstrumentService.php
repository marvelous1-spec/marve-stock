<?php
declare(strict_types=1);

namespace App\Market;

use App\Core\Database;

final class InstrumentService
{
    public function __construct(private ?MarketDataService $market = null)
    {
        $this->market ??= new MarketDataService();
    }

    /** @return array<string, mixed> */
    public function detail(string $symbol): array
    {
        $symbol = strtoupper(trim($symbol));
        if (!preg_match('/\A[A-Z0-9.]{1,32}\z/', $symbol)) {
            throw new \DomainException('Select a valid NGX market symbol.');
        }

        $overview = $this->market->overview();
        $instrument = null;
        foreach ($overview['items'] as $item) {
            if (($item['symbol'] ?? '') === $symbol) {
                $instrument = $item;
                break;
            }
        }

        $history = $this->history($symbol);
        if ($instrument === null && $history === []) {
            throw new \DomainException('This security is not available from the authorised market-data source.');
        }

        return [
            'instrument' => $instrument ?? $this->latestFromHistory($symbol),
            'history' => $history,
            'source' => $overview['source'] ?? 'cached',
            'status' => $overview['status'] ?? 'STALE',
            'notice' => count($history) < 2 ? 'Historical chart points will appear as authorised market quotes are collected.' : null,
        ];
    }

    /** @return list<array{observed_at:string,last_price:string}> */
    private function history(string $symbol): array
    {
        try {
            $query = Database::connection()->prepare('SELECT observed_at, last_price FROM market_quotes WHERE symbol = ? AND last_price IS NOT NULL AND observed_at >= DATE_SUB(NOW(), INTERVAL 90 DAY) ORDER BY observed_at ASC, id ASC LIMIT 500');
            $query->execute([$symbol]);
            return $query->fetchAll();
        } catch (\Throwable $exception) {
            error_log('Instrument history is unavailable: ' . $exception->getMessage());
            return [];
        }
    }

    /** @return array<string, mixed> */
    private function latestFromHistory(string $symbol): array
    {
        $query = Database::connection()->prepare('SELECT symbol, company_name, last_price, change_amount, change_percent, observed_at, data_status FROM market_quotes WHERE symbol = ? ORDER BY observed_at DESC, id DESC LIMIT 1');
        $query->execute([$symbol]);
        return $query->fetch() ?: ['symbol' => $symbol, 'company_name' => $symbol, 'last_price' => null, 'change_percent' => null, 'data_status' => 'STALE'];
    }
}
