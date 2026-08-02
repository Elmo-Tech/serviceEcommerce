<?php

declare(strict_types=1);

use App\Enums\Orders\PaymentStatus;
use App\Services\Orders\OrderPaymentSummaryService;

it('derives unpaid partial and paid states with remaining amount formatting', function () {
    $service = app(OrderPaymentSummaryService::class);

    $unpaid = $service->summarize('500.00', '0.00');
    $partial = $service->summarize('500.00', '200.00');
    $paid = $service->summarize('500.00', '500.00');
    $overpaid = $service->summarize('500.00', '700.00');

    expect($unpaid['paymentStatus'])->toBe(PaymentStatus::UNPAID)
        ->and($unpaid['remainingAmount'])->toBe('500.00')
        ->and($partial['paymentStatus'])->toBe(PaymentStatus::PARTIALLY_PAID)
        ->and($partial['remainingAmount'])->toBe('300.00')
        ->and($paid['paymentStatus'])->toBe(PaymentStatus::PAID)
        ->and($paid['remainingAmount'])->toBe('0.00')
        ->and($overpaid['paymentStatus'])->toBe(PaymentStatus::PAID)
        ->and($overpaid['remainingAmount'])->toBe('-200.00');
});
