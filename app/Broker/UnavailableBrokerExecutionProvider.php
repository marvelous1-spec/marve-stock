<?php
declare(strict_types=1);

namespace App\Broker;

final class UnavailableBrokerExecutionProvider implements BrokerExecutionProviderInterface
{
    public function status(): array
    {
        return [
            'status' => 'NOT_CONNECTED',
            'source' => null,
            'notice' => 'Trade execution is unavailable until an approved broker is configured.',
        ];
    }
}
