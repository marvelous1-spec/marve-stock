<?php
declare(strict_types=1);

namespace App\Auth;

use App\Core\Database;
use PDOException;

final class AuthService
{
    public static function register(string $first, string $last, string $email, string $password): int
    {
        $first = trim($first);
        $last = trim($last);
        if ($first === '' || $last === '' || strlen($first) > 100 || strlen($last) > 100 || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 12) {
            throw new \DomainException('Enter your first name, last name, a valid email, and a password of at least 12 characters.');
        }
        $email = strtolower(trim($email));
        $db = Database::connection();
        $existing = $db->prepare('SELECT 1 FROM users WHERE email = ? LIMIT 1');
        $existing->execute([$email]);
        if ($existing->fetchColumn()) {
            throw new \DomainException('An account already exists for this email address.');
        }

        $db->beginTransaction();
        try {
            $role = $db->query("SELECT id FROM roles WHERE name = 'investor'")->fetchColumn();
            $q = $db->prepare('INSERT INTO users(role_id, first_name, last_name, email, password_hash, status) VALUES (?, ?, ?, ?, ?, "active")');
            if ($role === false) {
                throw new \RuntimeException('The investor role is not configured.');
            }
            $q->execute([$role, $first, $last, $email, password_hash($password, PASSWORD_DEFAULT)]); $userId = (int)$db->lastInsertId();
            $db->prepare('INSERT INTO audit_logs(actor_type, actor_id, action, ip_address) VALUES ("user", ?, "registration", ?)')->execute([$userId, $_SERVER['REMOTE_ADDR'] ?? null]);
            $db->commit();
            return $userId;
        } catch (\Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            if ($e instanceof PDOException && $e->getCode() === '23000') {
                throw new \DomainException('An account already exists for this email address.');
            }
            throw $e;
        }
    }

    public static function login(string $email, string $password): int
    {
        $db = Database::connection(); $q = $db->prepare('SELECT id, first_name, password_hash, status FROM users WHERE email = ? LIMIT 1'); $q->execute([strtolower(trim($email))]); $u = $q->fetch();
        if (!$u || !password_verify($password, $u['password_hash'])) throw new \DomainException('Invalid email or password.');
        if (!in_array($u['status'], ['active', 'pending_email'], true)) throw new \DomainException('This account is not available for sign in.');
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
        $_SESSION['user_id'] = (int)$u['id']; $_SESSION['first_name'] = (string)$u['first_name'];
        $db->prepare('INSERT INTO audit_logs(actor_type, actor_id, action, ip_address) VALUES ("user", ?, "login", ?)')->execute([$u['id'], $_SERVER['REMOTE_ADDR'] ?? null]);
        return (int)$u['id'];
    }
}
