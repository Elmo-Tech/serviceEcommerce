<?php

declare(strict_types=1);

namespace App\Services\Orders;

use App\Enums\HttpStatusCode;
use App\Enums\Orders\DiscountType;
use App\Enums\Services\ServicePriceType;
use App\Enums\Services\ServicePricingInputType;
use App\Exceptions\ApiBusinessException;
use App\Models\Service;
use App\Models\ServicePricingOption;
use App\Models\ServicePricingOptionValue;
use Illuminate\Support\Collection;

class OrderPricingService
{
    /**
     * @param  list<array{pricingOptionId:int,valueIds:list<int>}>  $selectedOptions
     * @return array{unitPrice:string,selectedOptions:list<array>}
     */
    public function priceService(Service $service, array $selectedOptions = []): array
    {
        if ($service->price_type === ServicePriceType::FIXED && $selectedOptions !== []) {
            throw new ApiBusinessException(
                'orders.errors.invalid_pricing_selection',
                'INVALID_PRICING_SELECTION',
                HttpStatusCode::UNPROCESSABLE_ENTITY,
            );
        }

        $service->loadMissing('pricingOptions.values');

        $optionMap = $service->pricingOptions
            ->filter(fn (ServicePricingOption $option): bool => $option->deleted_at === null)
            ->keyBy(fn (ServicePricingOption $option): int => (int) $option->getKey());

        $this->ensureNoDuplicateOptionIds($selectedOptions);

        $selectedOptionSnapshots = [];
        $adjustments = 0.0;

        foreach ($selectedOptions as $selection) {
            $optionId = (int) $selection['pricingOptionId'];
            $valueIds = array_values(array_map('intval', $selection['valueIds']));
            $option = $optionMap->get($optionId);

            if (! $option instanceof ServicePricingOption) {
                throw new ApiBusinessException(
                    'orders.errors.pricing_option_not_found',
                    'PRICING_OPTION_NOT_FOUND',
                    HttpStatusCode::NOT_FOUND,
                );
            }

            $this->ensureNoDuplicateValueIds($valueIds);

            $values = $this->resolveSelectedValues($option, $valueIds);
            $this->validateSelectionCardinality($option, $values);

            $adjustments += $values->sum(fn (ServicePricingOptionValue $value): float => (float) $value->price_adjustment);

            $selectedOptionSnapshots[] = [
                'option' => $option,
                'values' => $values->all(),
            ];
        }

        $this->validateMissingRequiredOptions($optionMap, $selectedOptions);

        return [
            'unitPrice' => $this->format((float) $service->base_price + $adjustments),
            'selectedOptions' => $selectedOptionSnapshots,
        ];
    }

    public function calculateItemTotal(string|float|int $unitPrice, int $quantity): string
    {
        return $this->format(((float) $unitPrice) * $quantity);
    }

    /**
     * @param  list<string|float|int>  $itemTotals
     */
    public function calculateSubtotal(array $itemTotals): string
    {
        return $this->format(array_sum(array_map(fn ($total): float => (float) $total, $itemTotals)));
    }

    public function calculateDiscountAmount(
        string|float|int $subtotal,
        ?DiscountType $discountType,
        string|float|int $discountValue,
    ): string {
        $normalizedSubtotal = (float) $subtotal;
        $normalizedDiscountValue = (float) $discountValue;

        if ($discountType === null || $normalizedDiscountValue <= 0) {
            return '0.00';
        }

        if ($discountType === DiscountType::FIXED) {
            return $this->format($normalizedDiscountValue);
        }

        return $this->format(($normalizedSubtotal * $normalizedDiscountValue) / 100);
    }

    public function calculateTotal(
        string|float|int $subtotal,
        string|float|int $discountAmount,
    ): string {
        return $this->format((float) $subtotal - (float) $discountAmount);
    }

    /**
     * @param  list<array{pricingOptionId:int,valueIds:list<int>}>  $selectedOptions
     */
    private function validateMissingRequiredOptions(Collection $optionMap, array $selectedOptions): void
    {
        $selectedIds = collect($selectedOptions)
            ->map(fn (array $selection): int => (int) $selection['pricingOptionId'])
            ->all();

        $missingRequired = $optionMap
            ->filter(fn (ServicePricingOption $option): bool => (bool) $option->is_required)
            ->first(fn (ServicePricingOption $option): bool => ! in_array((int) $option->getKey(), $selectedIds, true));

        if ($missingRequired instanceof ServicePricingOption) {
            throw new ApiBusinessException(
                'orders.errors.invalid_pricing_selection',
                'INVALID_PRICING_SELECTION',
                HttpStatusCode::UNPROCESSABLE_ENTITY,
            );
        }
    }

    /**
     * @param  list<int>  $valueIds
     * @return Collection<int, ServicePricingOptionValue>
     */
    private function resolveSelectedValues(ServicePricingOption $option, array $valueIds): Collection
    {
        $values = $option->values
            ->filter(fn (ServicePricingOptionValue $value): bool => $value->deleted_at === null && (bool) $value->is_active)
            ->keyBy(fn (ServicePricingOptionValue $value): int => (int) $value->getKey());

        $selectedValues = collect();

        foreach ($valueIds as $valueId) {
            $value = $values->get($valueId);

            if (! $value instanceof ServicePricingOptionValue) {
                throw new ApiBusinessException(
                    'orders.errors.pricing_option_value_not_found',
                    'PRICING_OPTION_VALUE_NOT_FOUND',
                    HttpStatusCode::NOT_FOUND,
                );
            }

            $selectedValues->push($value);
        }

        return $selectedValues;
    }

    /**
     * @param  Collection<int, ServicePricingOptionValue>  $values
     */
    private function validateSelectionCardinality(ServicePricingOption $option, Collection $values): void
    {
        $count = $values->count();
        $singleValueInputs = [ServicePricingInputType::SELECT, ServicePricingInputType::RADIO];
        $multiValueInputs = [ServicePricingInputType::MULTI_SELECT, ServicePricingInputType::CHECKBOX];

        if (in_array($option->input_type, $singleValueInputs, true)) {
            if ($option->is_required && $count !== 1) {
                throw new ApiBusinessException(
                    'orders.errors.invalid_pricing_selection',
                    'INVALID_PRICING_SELECTION',
                    HttpStatusCode::UNPROCESSABLE_ENTITY,
                );
            }

            if (! $option->is_required && $count > 1) {
                throw new ApiBusinessException(
                    'orders.errors.invalid_pricing_selection',
                    'INVALID_PRICING_SELECTION',
                    HttpStatusCode::UNPROCESSABLE_ENTITY,
                );
            }
        }

        if (in_array($option->input_type, $multiValueInputs, true) && $option->is_required && $count < 1) {
            throw new ApiBusinessException(
                'orders.errors.invalid_pricing_selection',
                'INVALID_PRICING_SELECTION',
                HttpStatusCode::UNPROCESSABLE_ENTITY,
            );
        }
    }

    /**
     * @param  list<array{pricingOptionId:int,valueIds:list<int>}>  $selectedOptions
     */
    private function ensureNoDuplicateOptionIds(array $selectedOptions): void
    {
        $optionIds = array_map(fn (array $selection): int => (int) $selection['pricingOptionId'], $selectedOptions);

        if (count($optionIds) !== count(array_unique($optionIds))) {
            throw new ApiBusinessException(
                'orders.errors.invalid_pricing_selection',
                'INVALID_PRICING_SELECTION',
                HttpStatusCode::UNPROCESSABLE_ENTITY,
            );
        }
    }

    /**
     * @param  list<int>  $valueIds
     */
    private function ensureNoDuplicateValueIds(array $valueIds): void
    {
        if (count($valueIds) !== count(array_unique($valueIds))) {
            throw new ApiBusinessException(
                'orders.errors.invalid_pricing_selection',
                'INVALID_PRICING_SELECTION',
                HttpStatusCode::UNPROCESSABLE_ENTITY,
            );
        }
    }

    private function format(float $value): string
    {
        return number_format(round($value, 2), 2, '.', '');
    }
}
