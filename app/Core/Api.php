<?php
declare(strict_types=1);

namespace App\Core;

final class Api
{
    /** @param array<string,mixed> $data */
    public static function ok(array $data = [], string $message = 'Request completed successfully'): never
    {
        self::send(200, ['success' => true, 'message' => $message, 'data' => $data]);
    }
    public static function fail(string $message, string $code = 'REQUEST_FAILED', int $status = 400): never
    {
        self::send($status, ['success' => false, 'message' => $message, 'error_code' => $code]);
    }
    /** @param array<string,mixed> $body */
    private static function send(int $status, array $body): never
    {
        http_response_code($status); header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store'); echo json_encode($body, JSON_THROW_ON_ERROR); exit;
    }
}
