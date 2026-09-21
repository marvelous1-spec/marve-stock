<?php
declare(strict_types=1);

namespace App\Broker;

interface BrokerExecutionProviderInterface
{
    /** @return array{status:string,source:?string,notice:string} */
    public function status(): array;
}
