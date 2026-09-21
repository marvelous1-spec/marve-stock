<?php
declare(strict_types=1);

spl_autoload_register(static function (string $class): void { if (str_starts_with($class, 'App\\')) { $path = __DIR__ . '/' . str_replace('\\', '/', substr($class, 4)) . '.php'; if (is_file($path)) require $path; }});
\App\Security\RequestSecurity::startSession();
header('X-Content-Type-Options: nosniff'); header('X-Frame-Options: DENY'); header('Referrer-Policy: strict-origin-when-cross-origin'); header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
header("Content-Security-Policy: default-src 'self'; style-src 'self'; script-src 'self'; connect-src 'self' wss:; img-src 'self' data: https://pbs.twimg.com; frame-src https://checkout.paystack.com");
if (\App\Config\Env::get('APP_ENV') === 'production') header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
