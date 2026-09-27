<?php
declare(strict_types=1);

require __DIR__ . '/app/bootstrap.php';
?><!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sign in | Marve Invest</title>
    <link rel="stylesheet" href="public/assets/css/app.css">
    <link rel="stylesheet" href="public/assets/css/landing.css">
    <link rel="stylesheet" href="public/assets/css/brand.css">
</head>
<body class="landing-page">
    <main>
        <section class="about-section">
            <div class="about-heading">
                <p class="caption">ACCOUNT ACCESS</p>
                <h1>Sign in to Marve.</h1>
                <p id="loginIntro">Use your verified email address to access your account.</p>
            </div>
            <form id="loginPageForm" class="market-card" novalidate>
                <label>Email<input id="loginEmail" name="email" type="email" autocomplete="email" required></label>
                <label>Password<span class="password-field"><input id="loginPassword" name="password" type="password" autocomplete="current-password" minlength="12" required><button class="password-toggle" type="button" id="loginPasswordToggle" aria-label="Show password" aria-pressed="false">Show</button></span></label>
                <button class="primary" type="submit">Sign in</button>
                <p class="form-note" id="loginPageMessage" role="status"></p>
            </form>
        </section>
    </main>
    <script src="public/assets/js/login.js"></script>
</body>
</html>
