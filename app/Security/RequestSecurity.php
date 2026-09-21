<?php
declare(strict_types=1);

namespace App\Security;

use App\Config\Env;
use App\Core\Api;
use App\Core\Database;

final class RequestSecurity
{
    public static function startSession(): void
    {
        session_name('ngx_session');
        session_set_cookie_params(['httponly' => true, 'secure' => Env::get('APP_ENV') === 'production', 'samesite' => 'Lax', 'path' => '/']);
        session_start();
        $_SESSION['created_at'] ??= time(); $_SESSION['last_active_at'] ??= time();
        if (time() - (int) $_SESSION['created_at'] > 28800 || time() - (int) $_SESSION['last_active_at'] > 1800) { self::logout(); }
        $_SESSION['last_active_at'] = time(); $_SESSION['csrf'] ??= bin2hex(random_bytes(32));
    }
    public static function requireCsrf(): void
    {
        $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
        $allowed = array_filter(array_map('trim', explode(',', (string)Env::get('TRUSTED_ORIGINS', ''))));
        if ($origin !== '' && !in_array($origin, $allowed, true)) Api::fail('Request origin is not permitted.', 'ORIGIN_REJECTED', 403);
        if (!is_string($token) || !hash_equals((string) ($_SESSION['csrf'] ?? ''), $token)) Api::fail('Your session could not be verified.', 'CSRF_FAILED', 419);
    }
    public static function userId(): int { return (int) ($_SESSION['user_id'] ?? 0); }
    public static function requireUser(): int { $id = self::userId(); if (!$id) Api::fail('Authentication is required.', 'UNAUTHORIZED', 401); return $id; }
    public static function logout(): never { self::destroySession(); Api::fail('Your session has expired.', 'SESSION_EXPIRED', 401); }
    public static function destroySession(): void
    {
        $_SESSION = [];
        if (session_status() === PHP_SESSION_ACTIVE) {
            setcookie(session_name(), '', ['expires' => time() - 3600, 'path' => '/', 'httponly' => true, 'secure' => Env::get('APP_ENV') === 'production', 'samesite' => 'Lax']);
            session_destroy();
        }
    }
    public static function rateLimit(string $scope, int $limit, int $window): void
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown'; $key = hash('sha256', $scope.'|'.$ip); $db = Database::connection();
        $db->beginTransaction();
        try {
            $q = $db->prepare('SELECT id, attempts, window_started_at FROM rate_limits WHERE rate_key = ? FOR UPDATE'); $q->execute([$key]); $row = $q->fetch();
            if (!$row || strtotime($row['window_started_at']) + $window < time()) {
                $db->prepare('INSERT INTO rate_limits(rate_key, attempts, window_started_at) VALUES (?, 1, NOW()) ON DUPLICATE KEY UPDATE attempts=1, window_started_at=NOW()')->execute([$key]);
            } elseif ((int)$row['attempts'] >= $limit) { $db->rollBack(); Api::fail('Too many requests. Please try again later.', 'RATE_LIMITED', 429); }
            else $db->prepare('UPDATE rate_limits SET attempts = attempts + 1 WHERE id = ?')->execute([$row['id']]);
            $db->commit();
        } catch (\Throwable $e) { if ($db->inTransaction()) $db->rollBack(); throw $e; }
    }
}
