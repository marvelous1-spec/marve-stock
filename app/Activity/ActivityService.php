<?php
declare(strict_types=1);
namespace App\Activity;
use App\Core\Database;
final class ActivityService
{
    public function recent(int $userId): array
    {
        $q=Database::connection()->prepare('SELECT action,created_at FROM audit_logs WHERE actor_type="user" AND actor_id=? ORDER BY created_at DESC,id DESC LIMIT 20'); $q->execute([$userId]); return $q->fetchAll();
    }
}
