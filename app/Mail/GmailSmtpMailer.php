<?php
declare(strict_types=1);

namespace App\Mail;

use App\Config\Env;

final class GmailSmtpMailer implements MailerInterface
{
    private const HOST = 'ssl://smtp.gmail.com';
    private const PORT = 465;

    public function sendVerification(string $recipient, string $firstName, string $verificationCode): void
    {
        $username = strtolower(trim((string) Env::get('GMAIL_SMTP_USERNAME', '')));
        $appPassword = preg_replace('/\s+/', '', (string) Env::get('GMAIL_SMTP_APP_PASSWORD', ''));
        $fromAddress = $this->addressFrom((string) Env::get('MAIL_FROM', ''));

        if (!filter_var($username, FILTER_VALIDATE_EMAIL) || $appPassword === '' || $fromAddress === '') {
            throw new \RuntimeException('Gmail SMTP is not configured.');
        }
        if (!hash_equals($username, strtolower($fromAddress))) {
            throw new \RuntimeException('MAIL_FROM must use the Gmail SMTP address.');
        }
        if (!filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
            throw new \RuntimeException('The verification recipient is invalid.');
        }

        $socket = @stream_socket_client(self::HOST . ':' . self::PORT, $errorNumber, $errorMessage, 15, STREAM_CLIENT_CONNECT);
        if (!is_resource($socket)) {
            throw new \RuntimeException('Unable to connect to Gmail SMTP.');
        }

        stream_set_timeout($socket, 20);
        try {
            $this->expect($socket, [220]);
            $this->command($socket, 'EHLO localhost', [250]);
            $this->command($socket, 'AUTH LOGIN', [334]);
            $this->command($socket, base64_encode($username), [334]);
            $this->command($socket, base64_encode($appPassword), [235]);
            $this->command($socket, 'MAIL FROM:<' . $fromAddress . '>', [250]);
            $this->command($socket, 'RCPT TO:<' . $recipient . '>', [250, 251]);
            $this->command($socket, 'DATA', [354]);

            $safeName = htmlspecialchars($firstName, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $safeCode = htmlspecialchars($verificationCode, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $message = implode("\r\n", [
                'From: Marve Invest <' . $fromAddress . '>',
                'To: <' . $recipient . '>',
                'Subject: Your Marve Invest verification code',
                'MIME-Version: 1.0',
                'Content-Type: text/html; charset=UTF-8',
                'Content-Transfer-Encoding: 8bit',
                '',
                '<p>Hello ' . $safeName . ',</p><p>Your Marve Invest verification code is:</p><p style="font-size:28px;font-weight:700;letter-spacing:6px">' . $safeCode . '</p><p>This code expires in 15 minutes. Do not share it with anyone.</p>',
                '.',
            ]);
            $this->command($socket, $message, [250]);
            $this->command($socket, 'QUIT', [221]);
        } finally {
            fclose($socket);
        }
    }

    private function addressFrom(string $from): string
    {
        if (preg_match('/<([^<>\r\n]+)>/', $from, $matches)) {
            return trim($matches[1]);
        }
        return trim(str_replace(["\r", "\n"], '', $from));
    }

    /** @param resource $socket @param list<int> $expectedCodes */
    private function command($socket, string $command, array $expectedCodes): void
    {
        if (fwrite($socket, $command . "\r\n") === false) {
            throw new \RuntimeException('Gmail SMTP did not accept the request.');
        }
        $this->expect($socket, $expectedCodes);
    }

    /** @param resource $socket @param list<int> $expectedCodes */
    private function expect($socket, array $expectedCodes): void
    {
        $response = '';
        do {
            $line = fgets($socket, 2048);
            if ($line === false) {
                throw new \RuntimeException('Gmail SMTP did not respond.');
            }
            $response .= $line;
        } while (isset($line[3]) && $line[3] === '-');

        $code = (int) substr($response, 0, 3);
        if (!in_array($code, $expectedCodes, true)) {
            throw new \RuntimeException('Gmail SMTP rejected delivery.');
        }
    }
}
