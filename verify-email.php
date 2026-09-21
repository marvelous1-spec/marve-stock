<?php
declare(strict_types=1);

require __DIR__ . '/app/bootstrap.php';

http_response_code(410);
$message = 'Email verification is not required for this application.';
?><!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Email verification | Marve Invest</title>
    <link rel="stylesheet" href="public/assets/css/style.css">
</head>
<body>
    <main>
        <h1>Email verification</h1>
        <p><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></p>
        <p><a href="/marve-stock/">Return to Marve Invest</a></p>
    </main>
</body>
</html>
