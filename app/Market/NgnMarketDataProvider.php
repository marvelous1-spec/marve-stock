<?php
declare(strict_types=1);

namespace App\Market;

use App\Config\Env;
use Closure;

final class NgnMarketDataProvider implements MarketDataProviderInterface
{
    /** @var Closure(string, string):array<string,mixed>|null */
    private ?Closure $transport;

    /**
     * The optional transport is only for tests. Production requests use cURL.
     *
     * @param Closure(string, string):array<string,mixed>|null $transport
     */
    public function __construct(?Closure $transport = null)
    {
        $this->transport = $transport;
    }

    public function overview(): array
    {
        $baseUrl = rtrim((string) Env::get('MARKET_DATA_API_URL', ''), '/');
        $apiKey = trim((string) Env::get('MARKET_DATA_API_KEY', ''));
        if (($baseUrl === '' || $apiKey === '') && $this->transport === null) {
            return $this->unavailable('NGN Market credentials are not configured.');
        }

        try {
            $payload = $this->transport
                ? ($this->transport)($baseUrl, $apiKey)
                : $this->request($baseUrl, $apiKey);

            $items = $this->normalizeItems($payload);
            return [
                'status' => $this->entitlement(),
                'as_of' => $items[0]['observed_at'] ?? null,
                'source' => 'NGN Market',
                'items' => $items,
            ];
        } catch (\Throwable $exception) {
            error_log('NGN Market request failed: ' . $exception->getMessage());
            return $this->unavailable('NGN Market is currently unavailable.');
        }
    }

    public function instrument(string $symbol): ?array
    {
        foreach ($this->overview()['items'] as $item) {
            if (($item['symbol'] ?? '') === strtoupper($symbol)) {
                return $item;
            }
        }

        return null;
    }

    /** @return array{status:string,is_open:?bool,closes_at:?string,next_opens_at:?string,source:string,notice:?string} */
    public function marketStatus(): array
    {
        $baseUrl = rtrim((string) Env::get('MARKET_DATA_API_URL', ''), '/');
        $apiKey = trim((string) Env::get('MARKET_DATA_API_KEY', ''));
        if ($baseUrl === '' || $apiKey === '') {
            return $this->unavailableMarketStatus('NGN Market credentials are not configured.');
        }

        try {
            $payload = $this->request($baseUrl, $apiKey, '/v1/market/status');
            $data = is_array($payload['data'] ?? null) ? $payload['data'] : $payload;
            $rawStatus = strtoupper((string) ($data['status'] ?? $data['market_status'] ?? $data['state'] ?? ''));
            $isOpen = $this->booleanOrNull($data['is_open'] ?? $data['isOpen'] ?? $data['open'] ?? null);
            if ($isOpen === null && in_array($rawStatus, ['OPEN', 'TRADING', 'LIVE'], true)) $isOpen = true;
            if ($isOpen === null && in_array($rawStatus, ['CLOSED', 'CLOSE', 'HOLIDAY', 'PRE_OPEN', 'PRE-CLOSE'], true)) $isOpen = false;

            return [
                'status' => $isOpen === true ? 'OPEN' : ($isOpen === false ? 'CLOSED' : ($rawStatus !== '' ? $rawStatus : 'UNKNOWN')),
                'is_open' => $isOpen,
                'closes_at' => $this->firstString($data, ['closes_at', 'close_at', 'session_close', 'close_time', 'next_close']),
                'next_opens_at' => $this->firstString($data, ['next_opens_at', 'next_open', 'opens_at', 'open_time']),
                'source' => 'NGN Market',
                'notice' => null,
            ];
        } catch (\Throwable $exception) {
            error_log('NGN Market status request failed: ' . $exception->getMessage());
            return $this->unavailableMarketStatus('NGN Market session status is currently unavailable.');
        }
    }

    /** @return array<string,mixed> */
    private function request(string $baseUrl, string $apiKey, string $path = '/v1/companies'): array
    {
        if (filter_var($baseUrl, FILTER_VALIDATE_URL) === false) {
            throw new \RuntimeException('MARKET_DATA_API_URL is invalid.');
        }
        if (!function_exists('curl_init')) {
            throw new \RuntimeException('PHP cURL is unavailable.');
        }

        $request = curl_init($baseUrl . $path);
        if ($request === false) {
            throw new \RuntimeException('Unable to create the provider request.');
        }

        curl_setopt_array($request, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 8,
            CURLOPT_CONNECTTIMEOUT => 3,
            CURLOPT_HTTPHEADER => [
                'Accept: application/json',
                'Authorization: Bearer ' . $apiKey,
                'User-Agent: marve-invest/1.0',
            ],
        ]);

        $body = curl_exec($request);
        $status = (int) curl_getinfo($request, CURLINFO_RESPONSE_CODE);
        $error = curl_error($request);
        curl_close($request);

        if ($body === false || $status < 200 || $status >= 300) {
            throw new \RuntimeException($error !== '' ? $error : 'Provider returned HTTP ' . $status . '.');
        }

        $payload = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($payload) || ($payload['success'] ?? true) !== true) {
            throw new \RuntimeException('Provider returned an invalid response.');
        }

        return $payload;
    }

    /** @return array<int,array<string,mixed>> */
    private function normalizeItems(array $payload): array
    {
        $rows = $payload['data']['data'] ?? null;
        if (!is_array($rows) || !$this->isList($rows)) {
            throw new \RuntimeException('Provider response does not contain a company list.');
        }

        $items = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $symbol = strtoupper(trim((string) ($row['symbol'] ?? '')));
            $price = $row['price'] ?? null;
            if ($symbol === '' || !is_numeric($price)) {
                continue;
            }

            $items[] = [
                'symbol' => $symbol,
                'company_name' => $this->stringOrNull($row['name'] ?? null),
                'last_price' => (string) $price,
                'change_amount' => $this->numberOrNull($row['price_change'] ?? null),
                'change_percent' => $this->numberOrNull($row['price_change_percent'] ?? null),
                'observed_at' => $this->stringOrNull($row['last_updated'] ?? null),
                'data_status' => $this->entitlement(),
            ];
        }

        return $items;
    }

    private function entitlement(): string
    {
        $value = strtoupper((string) Env::get('MARKET_DATA_ENTITLEMENT', 'DELAYED'));
        return in_array($value, ['LIVE', 'DELAYED', 'END_OF_DAY'], true) ? $value : 'DELAYED';
    }

    /** @return array<string,mixed> */
    private function unavailable(string $notice): array
    {
        return ['status' => 'UNAVAILABLE', 'as_of' => null, 'source' => 'NGN Market', 'items' => [], 'notice' => $notice];
    }

    /** @return array{status:string,is_open:null,closes_at:null,next_opens_at:null,source:string,notice:string} */
    private function unavailableMarketStatus(string $notice): array
    {
        return ['status' => 'UNAVAILABLE', 'is_open' => null, 'closes_at' => null, 'next_opens_at' => null, 'source' => 'NGN Market', 'notice' => $notice];
    }

    private function booleanOrNull(mixed $value): ?bool
    {
        if (is_bool($value)) return $value;
        if (is_int($value) && in_array($value, [0, 1], true)) return $value === 1;
        if (is_string($value)) {
            $value = strtolower(trim($value));
            if (in_array($value, ['true', '1', 'open'], true)) return true;
            if (in_array($value, ['false', '0', 'closed'], true)) return false;
        }
        return null;
    }

    /** @param array<string,mixed> $data @param list<string> $keys */
    private function firstString(array $data, array $keys): ?string
    {
        foreach ($keys as $key) {
            if (is_string($data[$key] ?? null) && trim($data[$key]) !== '') return trim($data[$key]);
        }
        return null;
    }

    private function stringOrNull(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }

    private function numberOrNull(mixed $value): ?string
    {
        return is_numeric($value) ? (string) $value : null;
    }

    /** @param array<mixed> $items */
    private function isList(array $items): bool
    {
        $expected = 0;
        foreach ($items as $key => $_) {
            if ($key !== $expected++) {
                return false;
            }
        }
        return true;
    }
}
