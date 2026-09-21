<?php
declare(strict_types=1);

namespace App\Admin;

use App\Core\Database;

final class AdminService
{
    /** @return array<string, int> */
    public function overview(): array
    {
        $db = Database::connection();

        return [
            'total_users' => (int) $db->query('SELECT COUNT(*) FROM users')->fetchColumn(),
            'active_users' => (int) $db->query("SELECT COUNT(*) FROM users WHERE status = 'active'")->fetchColumn(),
            'suspended_users' => (int) $db->query("SELECT COUNT(*) FROM users WHERE status = 'suspended'")->fetchColumn(),
            'accounts_today' => (int) $db->query('SELECT COUNT(*) FROM users WHERE created_at >= CURDATE()')->fetchColumn(),
            'payments_pending' => (int) $db->query("SELECT COUNT(*) FROM payment_transactions WHERE status IN ('initialized', 'pending')")->fetchColumn(),
            'orders_open' => (int) $db->query("SELECT COUNT(*) FROM orders WHERE status IN ('pending', 'submitted', 'accepted', 'partially_filled', 'cancel_requested')")->fetchColumn(),
        ];
    }
}
