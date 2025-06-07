<?php

declare(strict_types=1);

namespace App\Tests;

use App\Exception\NoPaymentGatewayRoutedException;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use App\Entity\Payment;
use App\Gateways\Przelewy24PaymentGateway;
use App\Gateways\TpayPaymentGateway;
use App\Gateways\VoltPaymentGateway;
use App\Service\TrafficSplit;
use App\Exception\InvalidGatewayObjectException;
use App\Exception\InvalidWeightValueException;
use App\Exception\InvalidWeightsSumException;
use Random\RandomException;
use stdClass;

final class TrafficSplitTest extends TestCase
{
    /**
     * New payment
     *
     * @param int $id
     * @return Payment
     * @throws RandomException
     */
    private function makePaymentEntity(int $id): Payment
    {
        $payment = new Payment();
        $payment->setAmount((string)random_int(1, 1000));
        $payment->setCurrency('PLN');
        $payment->setStatus('CREATED');
        $payment->setPaymentMethod('card');
        $payment->setCreatedAt(new DateTimeImmutable());

        return $payment;
    }

    /**
     * Test of equal weights
     *
     * @return void
     * @throws InvalidGatewayObjectException
     * @throws InvalidWeightValueException
     * @throws InvalidWeightsSumException
     * @throws RandomException
     * @throws NoPaymentGatewayRoutedException
     */
    public function testEqualDistributionFourGateways(): void
    {
        $gatewayA = new Przelewy24PaymentGateway();
        $gatewayB = new TpayPaymentGateway();
        $gatewayC = new VoltPaymentGateway();
        $gatewayD = new Przelewy24PaymentGateway();

        $splitter = new TrafficSplit([
            ['gateway' => $gatewayA, 'weight' => 25],
            ['gateway' => $gatewayB, 'weight' => 25],
            ['gateway' => $gatewayC, 'weight' => 25],
            ['gateway' => $gatewayD, 'weight' => 25],
        ]);

        $total = 1000;
        for ($i = 0; $i < $total; $i++) {
            $splitter->handlePayment($this->makePaymentEntity($i));
        }

        $loads = [
            $gatewayA->getTrafficLoad(),
            $gatewayB->getTrafficLoad(),
            $gatewayC->getTrafficLoad(),
            $gatewayD->getTrafficLoad(),
        ];

        fwrite(STDOUT, PHP_EOL . "1. Equal distributed weights [25/25/25/25]: " . implode(' / ', $loads) . PHP_EOL);

        foreach ($loads as $load) {
            $this->assertGreaterThan($total * 0.20, $load);
            $this->assertLessThan($total * 0.30, $load);
        }
    }

    /**
     * Test of three different weights
     *
     * @return void
     * @throws InvalidGatewayObjectException
     * @throws InvalidWeightValueException
     * @throws InvalidWeightsSumException
     * @throws NoPaymentGatewayRoutedException
     * @throws RandomException
     */
    public function testWeightedDistribution(): void
    {
        $gatewayA = new Przelewy24PaymentGateway();
        $gatewayB = new TpayPaymentGateway();
        $gatewayC = new VoltPaymentGateway();

        $splitter = new TrafficSplit([
            ['gateway' => $gatewayA, 'weight' => 75],
            ['gateway' => $gatewayB, 'weight' => 10],
            ['gateway' => $gatewayC, 'weight' => 15],
        ]);

        $total = 1000;
        for ($i = 0; $i < $total; $i++) {
            $splitter->handlePayment($this->makePaymentEntity($i));
        }

        $loads = [
            $gatewayA->getTrafficLoad(),
            $gatewayB->getTrafficLoad(),
            $gatewayC->getTrafficLoad(),
        ];

        fwrite(STDOUT, PHP_EOL . "2. Different distributed weights [75/10/15]: " . implode(' / ', $loads) . PHP_EOL);

        $this->assertGreaterThan($total * 0.65, $loads[0]);
        $this->assertLessThan($total * 0.82, $loads[0]);
        $this->assertGreaterThan($total * 0.06, $loads[1]);
        $this->assertLessThan($total * 0.16, $loads[1]);
        $this->assertGreaterThan($total * 0.09, $loads[2]);
        $this->assertLessThan($total * 0.22, $loads[2]);
    }

    /**
     * Test of four different weights
     *
     * @return void
     * @throws InvalidGatewayObjectException
     * @throws InvalidWeightValueException
     * @throws InvalidWeightsSumException
     * @throws NoPaymentGatewayRoutedException
     * @throws RandomException
     */
    public function testWeightedDistributionVariant(): void
    {
        $gatewayA = new Przelewy24PaymentGateway();
        $gatewayB = new TpayPaymentGateway();
        $gatewayC = new VoltPaymentGateway();
        $gatewayD = new Przelewy24PaymentGateway();

        $splitter = new TrafficSplit([
            ['gateway' => $gatewayA, 'weight' => 30],
            ['gateway' => $gatewayB, 'weight' => 20],
            ['gateway' => $gatewayC, 'weight' => 45],
            ['gateway' => $gatewayD, 'weight' => 5],
        ]);

        $total = 1000;
        for ($i = 0; $i < $total; $i++) {
            $splitter->handlePayment($this->makePaymentEntity($i));
        }

        $loads = [
            $gatewayA->getTrafficLoad(),
            $gatewayB->getTrafficLoad(),
            $gatewayC->getTrafficLoad(),
            $gatewayD->getTrafficLoad(),
        ];

        fwrite(STDOUT, PHP_EOL . "3. Different distributed weights [30/20/45/5]: " . implode(' / ', $loads) . PHP_EOL);

        $this->assertGreaterThan($total * 0.24, $loads[0]);
        $this->assertLessThan($total * 0.36, $loads[0]);
        $this->assertGreaterThan($total * 0.13, $loads[1]);
        $this->assertLessThan($total * 0.27, $loads[1]);
        $this->assertGreaterThan($total * 0.38, $loads[2]);
        $this->assertLessThan($total * 0.52, $loads[2]);
        $this->assertGreaterThan($total * 0.01, $loads[3]);
        $this->assertLessThan($total * 0.09, $loads[3]);
    }

    /**
     * Test sum of weights different from 100
     *
     * @return void
     * @throws InvalidGatewayObjectException
     * @throws InvalidWeightValueException
     * @throws InvalidWeightsSumException
     */
    public function testThrowsExceptionIfWeightsDontSumTo100(): void
    {
        $this->expectException(InvalidWeightsSumException::class);
        $gatewayA = new Przelewy24PaymentGateway();
        $gatewayB = new TpayPaymentGateway();

        new TrafficSplit([
            ['gateway' => $gatewayA, 'weight' => 60],
            ['gateway' => $gatewayB, 'weight' => 50],
        ]);
    }

    /**
     * Test of weights equal zero
     *
     * @return void
     * @throws InvalidGatewayObjectException
     * @throws InvalidWeightValueException
     * @throws InvalidWeightsSumException
     */
    public function testThrowsExceptionIfAnyWeightIsZero(): void
    {
        $this->expectException(InvalidWeightValueException::class);
        $gatewayA = new Przelewy24PaymentGateway();
        $gatewayB = new TpayPaymentGateway();

        new TrafficSplit([
            ['gateway' => $gatewayA, 'weight' => 100],
            ['gateway' => $gatewayB, 'weight' => 0],
        ]);
    }

    /**
     * Test of negative weights
     *
     * @return void
     * @throws InvalidGatewayObjectException
     * @throws InvalidWeightValueException
     * @throws InvalidWeightsSumException
     */
    public function testThrowsExceptionIfAnyWeightIsNegative(): void
    {
        $this->expectException(InvalidWeightValueException::class);
        $gatewayA = new Przelewy24PaymentGateway();
        $gatewayB = new TpayPaymentGateway();

        new TrafficSplit([
            ['gateway' => $gatewayA, 'weight' => 80],
            ['gateway' => $gatewayB, 'weight' => -20],
        ]);
    }

    /**
     * Test od invalid gateway object
     *
     * @return void
     * @throws InvalidGatewayObjectException
     * @throws InvalidWeightValueException
     * @throws InvalidWeightsSumException
     */
    public function testThrowsExceptionIfGatewayIsNotValid(): void
    {
        $this->expectException(InvalidGatewayObjectException::class);
        $gatewayA = new Przelewy24PaymentGateway();

        new TrafficSplit([
            ['gateway' => $gatewayA, 'weight' => 100],
            ['gateway' => new stdClass(), 'weight' => 0],
        ]);
    }
}

