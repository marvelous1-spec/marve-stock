<?php
declare(strict_types=1);

namespace App\Mail;

use App\Config\Env;

final class ResendMailer implements MailerInterface
{
    public function sendVerification(string $recipient, string $firstName, string $verificationUrl): void
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
        $safeUrl = htmlspecialchars($verificationUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $payload = json_encode([
            'from' => $sender,
            'to' => [$recipient],
            'subject' => 'Verify your Marve Invest email address',
            'html' => '<p>Hello ' . $safeName . ',</p><p>Verify your email address to activate your account.</p><p><a href="' . $safeUrl . '">Verify email address</a></p><p>This link expires in 24 hours.</p>',
            'text' => "Hello {$firstName},\n\nVerify your email address:\n{$verificationUrl}\n\nThis link expires in 24 hours.",
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
