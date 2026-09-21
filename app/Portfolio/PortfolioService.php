<?php
declare(strict_types=1);

namespace App\Portfolio;

use App\Core\Database;

final class PortfolioService
{
    /** @return array{cash_balance:string,positions:list<array<string,mixed>>} */
    public function overview(int $userId): array
    {
        $db = Database::connection();
        $cash = $db->prepare('SELECT COALESCE(SUM(available_balance), 0) FROM wallets WHERE user_id = ? AND currency = "NGN"');
        $cash->execute([$userId]);
        $positions = $db->prepare('SELECT a.symbol, a.company_name,
            SUM(CASE WHEN o.side = "buy" THEN o.quantity ELSE -o.quantity END) AS quantity,
            q.last_price, q.observed_at, q.data_status
            FROM orders o
            JOIN assets a ON a.id = o.asset_id
            LEFT JOIN market_quotes q ON q.id = (
                SELECT mq.id FROM market_quotes mq WHERE mq.symbol = a.symbol ORDER BY mq.observed_at DESC, mq.id DESC LIMIT 1
            )
            WHERE o.user_id = ? AND o.status = "filled"
            GROUP BY a.id, a.symbol, a.company_name, q.last_price, q.observed_at, q.data_status
            HAVING quantity > 0
            ORDER BY a.symbol');
        $positions->execute([$userId]);
        return ['cash_balance' => (string) $cash->fetchColumn(), 'positions' => $positions->fetchAll()];
    }
}
