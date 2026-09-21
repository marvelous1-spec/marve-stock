<?php
declare(strict_types=1);
namespace App\Watchlist;
use App\Core\Database;
final class WatchlistService
{
    public function items(int $userId): array
    {
        $db = Database::connection(); $watchlistId = $this->defaultWatchlist($userId);
        $q = $db->prepare('SELECT a.symbol, a.company_name, q.last_price, q.change_percent, q.data_status FROM watchlist_items wi JOIN assets a ON a.id=wi.asset_id LEFT JOIN market_quotes q ON q.id=(SELECT mq.id FROM market_quotes mq WHERE mq.symbol=a.symbol ORDER BY mq.observed_at DESC,mq.id DESC LIMIT 1) WHERE wi.watchlist_id=? ORDER BY wi.sort_order,a.symbol');
        $q->execute([$watchlistId]); return $q->fetchAll();
    }
    public function add(int $userId, string $symbol): void
    {
        $symbol = strtoupper(trim($symbol));
        if (!preg_match('/\A[A-Z0-9.]{1,32}\z/', $symbol)) throw new \DomainException('Select a valid market symbol.');
        $db = Database::connection(); $quote = $db->prepare('SELECT company_name FROM market_quotes WHERE symbol=? ORDER BY observed_at DESC,id DESC LIMIT 1'); $quote->execute([$symbol]); $company = $quote->fetchColumn();
        if (!is_string($company) || $company === '') throw new \DomainException('This symbol is not available from the authorised market-data source.');
        $db->beginTransaction();
        try {
            $db->prepare('INSERT INTO assets(symbol,company_name,exchange) VALUES (?,?,"NGX") ON DUPLICATE KEY UPDATE company_name=VALUES(company_name)')->execute([$symbol,$company]);
            $asset=$db->prepare('SELECT id FROM assets WHERE symbol=? AND exchange="NGX"'); $asset->execute([$symbol]);
            $db->prepare('INSERT IGNORE INTO watchlist_items(watchlist_id,asset_id,sort_order) VALUES (?,?,0)')->execute([$this->defaultWatchlist($userId),$asset->fetchColumn()]);
            $db->prepare('INSERT INTO audit_logs(actor_type,actor_id,action,target_type,target_id) VALUES ("user",?,"watchlist_added","asset",?)')->execute([$userId,$symbol]); $db->commit();
        } catch (\Throwable $e) { if ($db->inTransaction()) $db->rollBack(); throw $e; }
    }
    private function defaultWatchlist(int $userId): int
    {
        $db=Database::connection(); $q=$db->prepare('SELECT id FROM watchlists WHERE user_id=? AND name="My watchlist"'); $q->execute([$userId]); $id=$q->fetchColumn();
        if ($id !== false) return (int)$id;
        $db->prepare('INSERT INTO watchlists(user_id,name) VALUES (?,"My watchlist")')->execute([$userId]); return (int)$db->lastInsertId();
    }
}
