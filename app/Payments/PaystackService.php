<?php
declare(strict_types=1);
namespace App\Payments;

use App\Config\Env;

final class PaystackService
{
    /** @return array<string,mixed> */
    public function initialize(string $email, string $reference, string $amountKobo, string $callbackUrl): array
    {
        $secret = Env::get('PAYSTACK_SECRET_KEY'); if (!$secret) throw new \RuntimeException('Payments are not configured.');
        $ch = curl_init('https://api.paystack.co/transaction/initialize');
        curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_RETURNTRANSFER => true, CURLOPT_HTTPHEADER => ['Authorization: Bearer '.$secret, 'Content-Type: application/json'], CURLOPT_POSTFIELDS => json_encode(['email'=>$email,'reference'=>$reference,'amount'=>$amountKobo,'currency'=>'NGN','callback_url'=>$callbackUrl]), CURLOPT_TIMEOUT => 15]);
        $response = curl_exec($ch); $status = curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
        $decoded = json_decode((string)$response, true); if ($status < 200 || $status >= 300 || !($decoded['status'] ?? false)) throw new \RuntimeException('Payment initialization failed.');
        return $decoded['data'];
    }
    public static function validSignature(string $raw, string $signature): bool
    {
        $secret = Env::get('PAYSTACK_WEBHOOK_SECRET') ?: Env::get('PAYSTACK_SECRET_KEY');
        return $secret !== null && hash_equals(hash_hmac('sha512', $raw, $secret), $signature);
    }
    /** @return array<string,mixed> */
    public function verify(string $reference): array
    {
        $secret = Env::get('PAYSTACK_SECRET_KEY'); if (!$secret) throw new \RuntimeException('Payments are not configured.');
        $ch = curl_init('https://api.paystack.co/transaction/verify/' . rawurlencode($reference));
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_HTTPHEADER => ['Authorization: Bearer '.$secret], CURLOPT_TIMEOUT => 15]);
        $response = curl_exec($ch); $status = curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
        $decoded = json_decode((string)$response, true); if ($status < 200 || $status >= 300 || !($decoded['status'] ?? false)) throw new \RuntimeException('Payment verification failed.');
        return $decoded['data'];
    }
}
