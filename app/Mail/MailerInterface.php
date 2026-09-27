<?php
declare(strict_types=1);

namespace App\Mail;

interface MailerInterface
{
    public function sendVerification(string $recipient, string $firstName, string $verificationCode): void;
}
