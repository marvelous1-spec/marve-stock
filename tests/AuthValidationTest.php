<?php
declare(strict_types=1);

spl_autoload_register(static function (string $class): void {
    if (!str_starts_with($class, 'App\\')) {
        return;
    }

    $path = dirname(__DIR__) . '/app/' . str_replace('\\', '/', substr($class, 4)) . '.php';
    if (is_file($path)) {
        require $path;
    }
});

function authExpect(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

try {
    App\Auth\AuthService::register('Test', 'User', 'invalid-email', '123456789012');
    throw new RuntimeException('Invalid email was accepted.');
} catch (DomainException $exception) {
    authExpect($exception->getMessage() === 'Enter your first name, last name, a valid email, and a password of at least 12 characters.', 'Invalid email message changed.');
}

try {
    App\Auth\AuthService::register('Test', 'User', 'test@example.com', 'short');
    throw new RuntimeException('Short password was accepted.');
} catch (DomainException $exception) {
    authExpect($exception->getMessage() === 'Enter your first name, last name, a valid email, and a password of at least 12 characters.', 'Short password message changed.');
}

echo "AuthValidationTest passed\n";
