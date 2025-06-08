<?php

declare(strict_types=1);

namespace App\Service;

use App\Interface\PaymentGatewayInterface;
use App\Entity\Payment;
use App\Exception\InvalidGatewayObjectException;
use App\Exception\InvalidWeightValueException;
use App\Exception\InvalidWeightsSumException;
use App\Exception\NoPaymentGatewayRoutedException;
use Random\RandomException;

class TrafficSplit
{
    private const string GATEWAY_KEY = 'gateway';
    private const string WEIGHT_KEY = 'weight';
    private const int VALID_PERCENTAGE = 100;
    /**
     * Array of gateways and weights
     *
     * @var array
     */
    private array $gatewaysWithWeights;
    /**
     * Sum of weights
     *
     * @var int
     */
    private int $totalWeight = 0;

    /**
     * TrafficSplit construct
     *
     * @param array $gatewaysWithWeights
     * @throws InvalidGatewayObjectException
     * @throws InvalidWeightValueException
     * @throws InvalidWeightsSumException
     */
    public function __construct(
        array $gatewaysWithWeights
    ) {
        $totalWeight = array_sum(array_column($gatewaysWithWeights, self::WEIGHT_KEY));
        $this->validateGatewaysWithWeights($gatewaysWithWeights, $totalWeight);
        $this->gatewaysWithWeights = $gatewaysWithWeights;
        $this->totalWeight = $totalWeight;
    }

    /**
     * Core logic of handling payment algorithm
     *
     * @param Payment $payment
     * @return void
     * @throws NoPaymentGatewayRoutedException
     * @throws RandomException
     */
    public function handlePayment(Payment $payment): void
    {
        $randomDecisionWeight = random_int(1, $this->totalWeight);
        $weightSum = 0;

        foreach ($this->gatewaysWithWeights as $weightedGateway) {
            $weightSum += $weightedGateway[self::WEIGHT_KEY];
            if ($randomDecisionWeight <= $weightSum) {
                $weightedGateway[self::GATEWAY_KEY]->process($payment);

                return;
            }
        }

        throw new NoPaymentGatewayRoutedException('No payment gateway was selected for routing.');
    }

    /**
     * Validate gateway weights array
     *
     * @param array $gatewaysWithWeights
     * @param int $weightSum
     * @return void
     * @throws InvalidGatewayObjectException
     * @throws InvalidWeightValueException
     * @throws InvalidWeightsSumException
     */
    private function validateGatewaysWithWeights(array $gatewaysWithWeights, int $weightSum): void
    {
        foreach ($gatewaysWithWeights as $weightedGateway) {
            $this->validateGatewayObject($weightedGateway[self::GATEWAY_KEY] ?? null);
            $this->validateGatewayWeight($weightedGateway[self::WEIGHT_KEY] ?? null);
        }

        $this->validateWeightSum($weightSum);
    }

    /**
     * Validate gateway object
     *
     * @param object|null $gateway
     * @return void
     * @throws InvalidGatewayObjectException
     */
    private function validateGatewayObject(?object $gateway): void
    {
        if (!$gateway instanceof PaymentGatewayInterface) {
            throw new InvalidGatewayObjectException('A valid Gateway must implement PaymentGatewayInterface');
        }
    }

    /**
     * Validate gateway weight
     *
     * @param mixed $weight
     * @return void
     * @throws InvalidWeightValueException
     */
    private function validateGatewayWeight(mixed $weight): void
    {
        if (!is_int($weight) || $weight <= 0) {
            throw new InvalidWeightValueException('Weight must be a positive integer');
        }
    }

    /**
     * Validate weight sum
     *
     * @param int $weightSum
     * @return void
     * @throws InvalidWeightsSumException
     */
    private function validateWeightSum(int $weightSum): void
    {
        if ($weightSum !== self::VALID_PERCENTAGE) {
            throw new InvalidWeightsSumException('Sum of weights must be exactly 100 (you provided ' . (string)$weightSum . ')');
        }
    }
}
