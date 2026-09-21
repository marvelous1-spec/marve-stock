<?php
declare(strict_types=1);
namespace App\Wallet;

use App\Core\Database;

final class LedgerService
{
    public static function creditDeposit(int $userId, string $reference, string $amount, string $currency = 'NGN'): void
    {
        $db = Database::connection(); $db->beginTransaction();
        try {
            $p = $db->prepare('SELECT id, status FROM payment_transactions WHERE provider_reference = ? FOR UPDATE'); $p->execute([$reference]); $payment = $p->fetch();
            if (!$payment || $payment['status'] === 'completed') { $db->rollBack(); return; }
            $w = $db->prepare('SELECT id FROM wallets WHERE user_id = ? AND currency = ? FOR UPDATE'); $w->execute([$userId, $currency]); $walletId = $w->fetchColumn();
            if (!$walletId) { $db->prepare('INSERT INTO wallets(user_id,currency) VALUES (?,?)')->execute([$userId,$currency]); $walletId = $db->lastInsertId(); }
            $db->prepare('INSERT INTO ledger_entries(reference, user_id, wallet_id, entry_type, amount, currency, status, description) VALUES (?, ?, ?, "credit", ?, ?, "completed", "Paystack deposit")')->execute([$reference,$userId,$walletId,$amount,$currency]);
            $db->prepare('UPDATE wallets SET available_balance=available_balance + ?, total_deposits=total_deposits + ? WHERE id=?')->execute([$amount,$amount,$walletId]);
            $db->prepare('UPDATE payment_transactions SET status="completed", completed_at=NOW() WHERE id=?')->execute([$payment['id']]);
            $db->commit();
        } catch (\Throwable $e) { if ($db->inTransaction()) $db->rollBack(); throw $e; }
    }
}
