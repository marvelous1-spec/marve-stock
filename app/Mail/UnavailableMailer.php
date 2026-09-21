<?php
declare(strict_types=1);

namespace App\Mail;

final class UnavailableMailer implements MailerInterface
{
    public function sendVerification(string $recipient, string $firstName, string $verificationUrl): void
    {
        throw new \RuntimeException('Email delivery is not configured.');
    }
}
