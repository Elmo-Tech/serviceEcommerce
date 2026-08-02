<?php

declare(strict_types=1);

namespace App\Services\Orders;

use App\Enums\HttpStatusCode;
use App\Exceptions\ApiBusinessException;
use App\Models\OrderNumberSequence;
use Illuminate\Support\Carbon;

class OrderNumberAllocator
{
    public function allocate(?Carbon $now = null): string
    {
        $timestamp = ($now ?? now())->clone()->utc();
        $businessDate = $timestamp->toDateString();

        $sequence = OrderNumberSequence::query()
            ->where('business_date', $businessDate)
            ->lockForUpdate()
            ->first();

        if (! $sequence instanceof OrderNumberSequence) {
            OrderNumberSequence::query()->create([
                'business_date' => $businessDate,
                'last_sequence' => 0,
            ]);

            $sequence = OrderNumberSequence::query()
                ->where('business_date', $businessDate)
                ->lockForUpdate()
                ->firstOrFail();
        }

        if ($sequence->last_sequence >= 9999) {
            throw new ApiBusinessException(
                'orders.errors.order_number_sequence_exhausted',
                'ORDER_NUMBER_SEQUENCE_EXHAUSTED',
                HttpStatusCode::CONFLICT,
            );
        }

        $nextSequence = $sequence->last_sequence + 1;

        $sequence->forceFill([
            'last_sequence' => $nextSequence,
        ])->save();

        return sprintf('ORD-%s-%04d', $timestamp->format('Ymd'), $nextSequence);
    }
}
