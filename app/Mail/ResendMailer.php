<?php
declare(strict_types=1);

namespace App\Mail;

use App\Config\Env;

final class ResendMailer implements MailerInterface
{
    public function sendVerification(string $recipient, string $firstName, string $verificationCode): void
    {
        $apiKey = trim((string) Env::get('RESEND_API_KEY', ''));
        $sender = trim((string) Env::get('MAIL_FROM', ''));
        if ($apiKey === '' || $sender === '') {
            throw new \RuntimeException('Email delivery is not configured.');
        }
        if (!function_exists('curl_init')) {
            throw new \RuntimeException('PHP cURL is unavailable for email delivery.');
        }

        $safeName = htmlspecialchars($firstName, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $safeCode = htmlspecialchars($verificationCode, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $payload = json_encode([
            'from' => $sender,
            'to' => [$recipient],
            'subject' => 'Your Marve Invest verification code',
            'html' => '<p>Hello ' . $safeName . ',</p><p>Use this code to verify your email address and activate your account:</p><p style="font-size:28px;font-weight:700;letter-spacing:6px">' . $safeCode . '</p><p>This code expires in 15 minutes. Do not share it with anyone.</p>',
            'text' => "Hello {$firstName},\n\nYour Marve Invest verification code is: {$verificationCode}\n\nThis code expires in 15 minutes. Do not share it with anyone.",
        ], JSON_THROW_ON_ERROR);

        $request = curl_init('https://api.resend.com/emails');
        if ($request === false) {
            throw new \RuntimeException('Unable to create the email request.');
        }
        curl_setopt_array($request, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $apiKey,
                'Content-Type: application/json',
                'Accept: application/json',
            ],
        ]);
        $response = curl_exec($request);
        $status = (int) curl_getinfo($request, CURLINFO_RESPONSE_CODE);
        $error = curl_error($request);
        curl_close($request);

        if ($response === false) {
            throw new \RuntimeException($error !== '' ? 'Email delivery failed.' : 'Email delivery failed.');
        }
        if ($status < 200 || $status >= 300) {
            throw new \RuntimeException('Email provider rejected delivery (HTTP ' . $status . ').');
        }
        $accepted = json_decode($response, true);
        if (!is_array($accepted) || !is_string($accepted['id'] ?? null) || $accepted['id'] === '') {
            throw new \RuntimeException('Email provider returned an invalid delivery response.');
        }
    }
}
