<?php

declare(strict_types=1);

namespace App\Services\Orders;

use App\Enums\HttpStatusCode;
use App\Exceptions\ApiBusinessException;
use App\Models\Order;
use App\Models\OrderIdempotencyKey;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class PublicOrderIdempotencyService
{
    public function __construct(
        private readonly CustomerOrderResolver $customerOrderResolver,
    ) {}

    /**
     * @param  array<string,mixed>  $payload
     */
    public function fingerprint(array $payload): string
    {
        $canonical = $this->canonicalize($payload);

        return hash('sha256', json_encode($canonical, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    }

    /**
     * @return array{reservation:OrderIdempotencyKey,isReplay:bool,replayOrder:?Order}
     */
    public function reserve(string $idempotencyKey, string $fingerprint, ?Carbon $now = null): array
    {
        $timestamp = ($now ?? now())->clone()->utc();

        DB::table('order_idempotency_keys')->insertOrIgnore([
            'idempotency_key' => $idempotencyKey,
            'request_fingerprint' => $fingerprint,
            'reserved_at' => $timestamp,
            'completed_at' => null,
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ]);

        $reservation = OrderIdempotencyKey::query()
            ->where('idempotency_key', $idempotencyKey)
            ->lockForUpdate()
            ->firstOrFail();

        if ($reservation->order instanceof Order) {
            if ($reservation->request_fingerprint !== $fingerprint) {
                throw new ApiBusinessException(
                    'orders.errors.idempotency_key_reused',
                    'IDEMPOTENCY_KEY_REUSED',
                    HttpStatusCode::CONFLICT,
                );
            }

            return [
                'reservation' => $reservation,
                'isReplay' => true,
                'replayOrder' => $reservation->order,
            ];
        }

        if ($reservation->request_fingerprint !== $fingerprint) {
            throw new ApiBusinessException(
                'orders.errors.idempotency_key_reused',
                'IDEMPOTENCY_KEY_REUSED',
                HttpStatusCode::CONFLICT,
            );
        }

        return [
            'reservation' => $reservation,
            'isReplay' => false,
            'replayOrder' => null,
        ];
    }

    public function complete(OrderIdempotencyKey $reservation, Order $order, ?Carbon $now = null): OrderIdempotencyKey
    {
        $timestamp = ($now ?? now())->clone()->utc();

        $reservation->forceFill([
            'order_id' => $order->getKey(),
            'completed_at' => $timestamp,
        ])->save();

        return $reservation->refresh();
    }

    /**
     * @param  array<string,mixed>  $payload
     * @return array<string,mixed>
     */
    private function canonicalize(array $payload): array
    {
        $canonical = $payload;

        if (isset($canonical['customer']) && is_array($canonical['customer'])) {
            if (isset($canonical['customer']['phone']) && is_string($canonical['customer']['phone'])) {
                $normalizedPhone = $this->customerOrderResolver->normalizeEgyptianPhone($canonical['customer']['phone']);
                $canonical['customer']['phone'] = $normalizedPhone ?? trim($canonical['customer']['phone']);
            }

            if (array_key_exists('email', $canonical['customer'])) {
                $canonical['customer']['email'] = $this->customerOrderResolver->normalizeEmail($canonical['customer']['email']);
            }
        }

        return $this->canonicalizeValue($canonical);
    }

    private function canonicalizeValue(mixed $value): mixed
    {
        if ($value instanceof UploadedFile) {
            return [
                'sha256' => hash_file('sha256', $value->getRealPath() ?: ''),
                'size' => (int) $value->getSize(),
                'mimeType' => $value->getMimeType(),
            ];
        }

        if (is_array($value)) {
            if ($this->isValueIdsList($value)) {
                $sorted = array_map('intval', $value);
                sort($sorted);

                return $sorted;
            }

            $canonical = [];
            $keys = array_keys($value);
            sort($keys);

            foreach ($keys as $key) {
                $canonical[(string) $key] = $this->canonicalizeValue($value[$key]);
            }

            return $canonical;
        }

        if (is_string($value)) {
            return trim($value);
        }

        return $value;
    }

    /**
     * @param  array<int|string,mixed>  $value
     */
    private function isValueIdsList(array $value): bool
    {
        if ($value === []) {
            return false;
        }

        $keys = array_keys($value);

        if ($keys !== range(0, count($value) - 1)) {
            return false;
        }

        return collect($value)->every(fn (mixed $item): bool => is_int($item) || (is_string($item) && ctype_digit($item)));
    }
}
