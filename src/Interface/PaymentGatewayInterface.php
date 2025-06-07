<?php

declare(strict_types=1);

namespace App\Interface;

use App\Entity\Payment;

interface PaymentGatewayInterface
{
    /**
     * Process payment
     *
     * @param Payment $payment
     * @return void
     */
    public function process(Payment $payment): void;

    /**
     * Get traffic load
     *
     * @return int
     */
    public function getTrafficLoad(): int;
}
