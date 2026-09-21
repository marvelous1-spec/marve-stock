<?php
declare(strict_types=1);

namespace App\Broker;

final class BrokerExecutionService
{
    private BrokerExecutionProviderInterface $provider;

    public function __construct(?BrokerExecutionProviderInterface $provider = null)
    {
        $this->provider = $provider ?? new UnavailableBrokerExecutionProvider();
    }

    /** @return array{status:string,source:?string,notice:string} */
    public function status(): array
    {
        return $this->provider->status();
    }
}
