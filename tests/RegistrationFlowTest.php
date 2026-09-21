<?php
declare(strict_types=1);

spl_autoload_register(static function (string $class): void {
    if (str_starts_with($class, 'App\\')) {
        $path = dirname(__DIR__) . '/app/' . str_replace('\\', '/', substr($class, 4)) . '.php';
        if (is_file($path)) require $path;
    }
});

use App\Auth\AuthService;
use App\Core\Database;

function registrationExpect(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_SESSION = [];
$suffix = bin2hex(random_bytes(8));
$email = 'registration-test-' . $suffix . '@example.test';
$password = 'CorrectHorseBatteryStaple1';
$db = Database::connection();
$userId = 0;

try {
    $userId = AuthService::register('Test', 'Investor', $email, $password);

    $user = $db->prepare('SELECT status, password_hash FROM users WHERE id = ?');
    $user->execute([$userId]);
    $row = $user->fetch();
    registrationExpect($row['status'] === 'active', 'New account was not activated.');
    registrationExpect(password_verify($password, $row['password_hash']), 'Password hash is invalid.');

    try {
        AuthService::register('Test', 'Investor', $email, $password);
        throw new RuntimeException('Duplicate email was accepted.');
    } catch (DomainException $exception) {
        registrationExpect($exception->getMessage() === 'An account already exists for this email address.', 'Duplicate email returned the wrong error.');
    }

    registrationExpect(AuthService::login($email, $password) === $userId, 'Verified user could not sign in.');

    echo "RegistrationFlowTest passed\n";
} finally {
    if ($userId > 0) {
        $db->prepare('DELETE FROM email_verifications WHERE user_id = ?')->execute([$userId]);
        $db->prepare('DELETE FROM audit_logs WHERE actor_id = ? AND actor_type = "user"')->execute([$userId]);
        $db->prepare('DELETE FROM users WHERE id = ?')->execute([$userId]);
    }
}
