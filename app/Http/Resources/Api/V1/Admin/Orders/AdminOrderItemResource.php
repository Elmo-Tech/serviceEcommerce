<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Admin\Orders;

use App\Models\OrderItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin OrderItem
 */
class AdminOrderItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'serviceId' => $this->service_id,
            'serviceSnapshot' => [
                'name' => $this->localizedValue($this->service_name_ar, $this->service_name_en),
                'slug' => $this->localizedValue($this->service_slug_ar, $this->service_slug_en),
                'priceType' => $this->price_type?->value,
                'basePrice' => $this->formatMoney($this->base_price),
            ],
            'quantity' => (int) $this->quantity,
            'unitPrice' => $this->formatMoney($this->unit_price),
            'itemTotal' => $this->formatMoney($this->item_total),
            'itemNote' => $this->item_note,
            'selectedOptions' => $this->selectedOptions->map(function ($selectedOption): array {
                return [
                    'id' => $selectedOption->id,
                    'pricingOptionId' => $selectedOption->pricing_option_id,
                    'name' => $this->localizedValue($selectedOption->option_name_ar, $selectedOption->option_name_en),
                    'inputType' => $selectedOption->input_type?->value,
                    'isRequired' => (bool) $selectedOption->is_required,
                    'values' => $selectedOption->values->map(function ($value): array {
                        return [
                            'id' => $value->id,
                            'pricingOptionValueId' => $value->pricing_option_value_id,
                            'label' => $this->localizedValue($value->value_label_ar, $value->value_label_en),
                            'priceAdjustment' => $this->formatMoney($value->price_adjustment),
                        ];
                    })->values()->all(),
                ];
            })->values()->all(),
            'answers' => $this->answers->map(function ($answer): array {
                return [
                    'id' => $answer->id,
                    'orderFieldId' => $answer->service_order_field_id,
                    'question' => $this->localizedValue($answer->question_ar, $answer->question_en),
                    'fieldType' => $answer->field_type?->value,
                    'isRequired' => (bool) $answer->is_required,
                    'answer' => $answer->answer,
                ];
            })->values()->all(),
            'attachments' => AdminOrderAttachmentResource::collection($this->whenLoaded('attachments'))->resolve(),
            'createdAt' => $this->created_at?->toJSON(),
            'updatedAt' => $this->updated_at?->toJSON(),
        ];
    }

    private function localizedValue(?string $arabicValue, ?string $englishValue): ?string
    {
        return app()->getLocale() === 'en'
            ? ($englishValue ?? $arabicValue)
            : ($arabicValue ?? $englishValue);
    }

    private function formatMoney(string|int|float|null $value): string
    {
        return number_format((float) $value, 2, '.', '');
    }
}
