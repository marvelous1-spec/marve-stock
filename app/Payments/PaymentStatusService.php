<?php
declare(strict_types=1);

namespace App\Payments;

use App\Config\Env;

final class PaymentStatusService
{
    /** @return array{status:string,source:?string,notice:string} */
    public function status(): array
    {
        $secret = trim((string) Env::get('PAYSTACK_SECRET_KEY', ''));
        $public = trim((string) Env::get('PAYSTACK_PUBLIC_KEY', ''));
        if ($secret !== '' && $public !== '') {
            return ['status' => 'CONFIGURED', 'source' => 'Paystack', 'notice' => 'Payments are configured server-side.'];
        }

        return ['status' => 'NOT_CONFIGURED', 'source' => null, 'notice' => 'Payments are not configured.'];
    }
}
