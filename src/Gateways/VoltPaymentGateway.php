<?php

declare(strict_types=1);

namespace App\Gateways;

use App\Interface\PaymentGatewayInterface;
use App\Entity\Payment;

class VoltPaymentGateway implements PaymentGatewayInterface
{
    public const VOLT_PAYMENT_GATEWAY_CODE = 'volt_payment_gateway';
    private int $trafficLoad = 0;

    /**
     * Process payment
     *
     * @param Payment $payment
     * @return void
     */
    public function process(Payment $payment): void
    {
        $this->trafficLoad++;
    }

    /**
     * Get traffic load
     *
     * @return int
     */
    public function getTrafficLoad(): int
    {
        return $this->trafficLoad;
    }
}
