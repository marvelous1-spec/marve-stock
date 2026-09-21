<?php
declare(strict_types=1);
require dirname(__DIR__, 2) . '/app/bootstrap.php';

use App\Activity\ActivityService; use App\Admin\AdminService; use App\Auth\AuthService; use App\Core\Api; use App\Core\Database; use App\Market\InstrumentService; use App\Market\MarketDataService; use App\Payments\PaystackService; use App\Platform\PlatformStatusService; use App\Portfolio\PortfolioService; use App\Security\RequestSecurity; use App\Wallet\LedgerService; use App\Watchlist\WatchlistService;

$method = $_SERVER['REQUEST_METHOD']; $route = trim((string)($_GET['route'] ?? ''), '/');
$rawBody = file_get_contents('php://input') ?: ''; $body = json_decode($rawBody ?: '{}', true) ?: [];
try {
    if ($method === 'GET' && $route === 'csrf') Api::ok(['token' => $_SESSION['csrf']]);
    if ($method === 'GET' && $route === 'market/overview') Api::ok((new MarketDataService())->overview());
    if ($method === 'GET' && $route === 'market/status') Api::ok((new MarketDataService())->marketStatus());
    if ($method === 'GET' && $route === 'market/instrument') Api::ok((new InstrumentService())->detail((string) ($_GET['symbol'] ?? '')));
    if ($method === 'GET' && $route === 'platform/status') Api::ok((new PlatformStatusService())->status());
    if ($method === 'GET' && $route === 'platform/connections') Api::ok((new PlatformStatusService())->connections());
    if ($method === 'POST' && $route === 'auth/register') { RequestSecurity::rateLimit('register', 5, 3600); RequestSecurity::requireCsrf(); $firstName = trim((string)($body['first_name']??'')); $id = AuthService::register($firstName,(string)($body['last_name']??''),(string)($body['email']??''),(string)($body['password']??'')); Api::ok(['user_id'=>$id, 'first_name'=>$firstName], 'Account created. You can now sign in.'); }
    if ($method === 'POST' && $route === 'auth/login') { RequestSecurity::rateLimit('login', 10, 900); RequestSecurity::requireCsrf(); $id = AuthService::login((string)($body['email']??''),(string)($body['password']??'')); Api::ok(['user_id'=>$id, 'first_name'=>$_SESSION['first_name'] ?? '', 'csrf'=>$_SESSION['csrf']], 'Signed in successfully.'); }
    if ($method === 'POST' && $route === 'auth/logout') { RequestSecurity::requireCsrf(); RequestSecurity::destroySession(); Api::ok([], 'You have been signed out.'); }
    if ($method === 'POST' && $route === 'payments/paystack/webhook') {
        $raw = $rawBody; if (!PaystackService::validSignature($raw, (string)($_SERVER['HTTP_X_PAYSTACK_SIGNATURE'] ?? ''))) Api::fail('Invalid webhook signature.', 'INVALID_SIGNATURE', 401);
        $event = json_decode($raw, true, 512, JSON_THROW_ON_ERROR); if (($event['event'] ?? '') !== 'charge.success') Api::ok([], 'Event acknowledged.');
        $data = $event['data'] ?? []; $reference = (string)($data['reference'] ?? ''); if ($reference === '') Api::fail('Missing payment reference.', 'INVALID_EVENT', 422);
        $db = Database::connection(); $eventId = (string)($data['id'] ?? $reference); $payloadHash = hash('sha256',$raw);
        $lock = $db->prepare('INSERT IGNORE INTO webhook_events(provider,event_id,payload_hash) VALUES ("paystack", ?, ?)'); $lock->execute([$eventId, $payloadHash]);
        $existing = $db->prepare('SELECT processed_at FROM webhook_events WHERE provider="paystack" AND event_id=?'); $existing->execute([$eventId]);
        if ($existing->fetchColumn() !== null) Api::ok([], 'Duplicate event acknowledged.');
        $payment = $db->prepare('SELECT user_id, amount, currency FROM payment_transactions WHERE provider="paystack" AND provider_reference=?'); $payment->execute([$reference]); $internal = $payment->fetch(); $verified = (new PaystackService())->verify($reference);
        $parts = explode('.', (string)($internal['amount'] ?? '0'), 2); $expectedKobo = (int)($parts[0] . str_pad(substr($parts[1] ?? '', 0, 2), 2, '0'));
        if (!$internal || ($verified['status'] ?? '') !== 'success' || (int)($verified['amount'] ?? 0) !== $expectedKobo || ($verified['currency'] ?? '') !== $internal['currency']) Api::fail('Payment verification mismatch.', 'PAYMENT_MISMATCH', 422);
        LedgerService::creditDeposit((int)$internal['user_id'], $reference, (string)$internal['amount'], (string)$internal['currency']);
        $db->prepare('UPDATE webhook_events SET processed_at=NOW() WHERE provider="paystack" AND event_id=? AND payload_hash=?')->execute([$eventId, $payloadHash]); Api::ok([], 'Payment recorded.');
    }
    if ($method === 'GET' && $route === 'me') {
        $id = RequestSecurity::requireUser();
        $role = Database::connection()->prepare('SELECT r.name FROM users u JOIN roles r ON r.id = u.role_id WHERE u.id = ?');
        $role->execute([$id]);
        Api::ok(['user_id'=>$id, 'first_name'=>$_SESSION['first_name'] ?? '', 'is_admin'=>$role->fetchColumn() === 'super_administrator', 'csrf'=>$_SESSION['csrf']]);
    }
    if ($method === 'GET' && $route === 'admin/overview') {
        $id = RequestSecurity::requireUser();
        $role = Database::connection()->prepare('SELECT r.name FROM users u JOIN roles r ON r.id = u.role_id WHERE u.id = ?');
        $role->execute([$id]);
        if ($role->fetchColumn() !== 'super_administrator') Api::fail('Administrator access is required.', 'FORBIDDEN', 403);
        Api::ok((new AdminService())->overview());
    }
    if ($method === 'GET' && $route === 'watchlist') { $id=RequestSecurity::requireUser(); Api::ok(['items'=>(new WatchlistService())->items($id)]); }
    if ($method === 'POST' && $route === 'watchlist/items') { RequestSecurity::requireCsrf(); $id=RequestSecurity::requireUser(); (new WatchlistService())->add($id,(string)($body['symbol']??'')); Api::ok([], 'Added to your watchlist.'); }
    if ($method === 'GET' && $route === 'activity') { $id=RequestSecurity::requireUser(); Api::ok(['items'=>(new ActivityService())->recent($id)]); }
    if ($method === 'GET' && $route === 'portfolio') { $id=RequestSecurity::requireUser(); Api::ok((new PortfolioService())->overview($id)); }
    Api::fail('Endpoint not found.', 'NOT_FOUND', 404);
} catch (\DomainException $e) {
    $status = $e->getMessage() === 'An account already exists for this email address.' ? 409 : 422;
    Api::fail($e->getMessage(), $status === 409 ? 'EMAIL_ALREADY_REGISTERED' : 'VALIDATION_FAILED', $status);
}
catch (\PDOException $e) {
    error_log('Database request failed: ' . $e->getCode());
    Api::fail('The account service is temporarily unavailable. Start MySQL in XAMPP, then try again.', 'DATABASE_UNAVAILABLE', 503);
}
catch (\Throwable $e) { error_log((string)$e); Api::fail('Unable to complete request.', 'SERVER_ERROR', 500); }
