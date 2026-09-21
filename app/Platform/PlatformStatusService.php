<?php
declare(strict_types=1);

namespace App\Platform;

use App\Broker\BrokerExecutionService;
use App\Market\MarketDataService;
use App\Payments\PaymentStatusService;

final class PlatformStatusService
{
    public function __construct(
        private ?MarketDataService $market = null,
        private ?BrokerExecutionService $broker = null,
        private ?PaymentStatusService $payments = null,
    ) {
        $this->market ??= new MarketDataService();
        $this->broker ??= new BrokerExecutionService();
        $this->payments ??= new PaymentStatusService();
    }

    /** @return array<string,mixed> */
    public function status(): array
    {
        return [
            'market' => $this->market->overview(),
            'execution' => $this->broker->status(),
            'payments' => $this->payments->status(),
        ];
    }

    /** @return array{execution:array<string,mixed>,payments:array<string,mixed>} */
    public function connections(): array
    {
        return [
            'execution' => $this->broker->status(),
            'payments' => $this->payments->status(),
        ];
    }
}
