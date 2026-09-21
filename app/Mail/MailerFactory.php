<?php
declare(strict_types=1);

namespace App\Mail;

use App\Config\Env;

final class MailerFactory
{
    public static function make(): MailerInterface
    {
        return strtolower((string) Env::get('MAIL_PROVIDER', 'none')) === 'resend'
            ? new ResendMailer()
            : new UnavailableMailer();
    }
}
