<?php
declare(strict_types=1);

require __DIR__ . '/app/bootstrap.php';

use App\Security\RequestSecurity;

$userId = RequestSecurity::requireUser();
$db = \App\Core\Database::connection();
$role = $db->prepare('SELECT r.name FROM users u JOIN roles r ON r.id = u.role_id WHERE u.id = ?');
$role->execute([$userId]);
if ($role->fetchColumn() !== 'super_administrator') {
    http_response_code(403);
    exit('Access denied.');
}
?><!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Marve Admin</title><link rel="stylesheet" href="public/assets/css/app.css"><link rel="stylesheet" href="public/assets/css/admin.css"></head>
<body class="admin-page"><main><header class="admin-header"><div><a class="admin-brand" href="/marve-stock/">marve<span>.</span></a><p class="caption">ADMINISTRATION</p><h1>Platform overview</h1></div><a class="outline" href="/marve-stock/">Return to market</a></header>
<section class="admin-status" aria-labelledby="connectionHeading"><div><p class="caption" id="connectionHeading">SERVICE CONNECTIONS</p><h2>Current platform state</h2></div><div id="connectionCards" class="connection-cards" aria-live="polite"></div></section>
<section><div class="section-heading"><div><p class="caption">OPERATIONS</p><h2>Activity at a glance</h2></div><span id="adminUpdated">Loading</span></div><div id="adminStats" class="admin-stats" aria-live="polite"></div></section>
<section class="admin-guidance"><p class="caption">ADMIN ACCESS</p><h2>Real records only.</h2><p>This area reports account, payment, and order records stored by the platform. It does not create trades, balances, or payment activity. Broker execution remains disabled until an approved provider is connected.</p></section>
</main><script src="public/assets/js/admin.js"></script></body></html>
