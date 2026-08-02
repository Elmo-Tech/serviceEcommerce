<?php

declare(strict_types=1);

namespace App\Services\Orders;

use App\Enums\Orders\PaymentStatus;

class OrderPaymentSummaryService
{
    public function summarize(string|int|float $total, string|int|float $paidAmount): array
    {
        return [
            'paymentStatus' => $this->derivePaymentStatus($total, $paidAmount),
            'remainingAmount' => $this->calculateRemainingAmount($total, $paidAmount),
        ];
    }

    public function derivePaymentStatus(string|int|float $total, string|int|float $paidAmount): PaymentStatus
    {
        $normalizedTotal = $this->toFloat($total);
        $normalizedPaidAmount = $this->toFloat($paidAmount);

        if ($normalizedPaidAmount <= 0.0) {
            return PaymentStatus::UNPAID;
        }

        if ($normalizedPaidAmount >= $normalizedTotal) {
            return PaymentStatus::PAID;
        }

        return PaymentStatus::PARTIALLY_PAID;
    }

    public function calculateRemainingAmount(string|int|float $total, string|int|float $paidAmount): string
    {
        return $this->format(
            $this->toFloat($total) - $this->toFloat($paidAmount),
        );
    }

    private function toFloat(string|int|float $value): float
    {
        return round((float) $value, 2);
    }

    private function format(float $value): string
    {
        return number_format($value, 2, '.', '');
    }
}
