<?php
declare(strict_types=1);

namespace App\Mail;

use App\Config\Env;

final class MailerFactory
{
    public static function make(): MailerInterface
    {
        return match (strtolower((string) Env::get('MAIL_PROVIDER', 'none'))) {
            'gmail', 'gmail_smtp' => new GmailSmtpMailer(),
            'resend' => new ResendMailer(),
            default => new UnavailableMailer(),
        };
    }
}
