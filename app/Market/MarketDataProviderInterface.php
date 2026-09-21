<?php
declare(strict_types=1);
namespace App\Market;

interface MarketDataProviderInterface
{
    /** @return array{status:string,as_of:?string,source:?string,items:array<int,array<string,mixed>>} */
    public function overview(): array;
    /** @return array<string,mixed>|null */
    public function instrument(string $symbol): ?array;
}
