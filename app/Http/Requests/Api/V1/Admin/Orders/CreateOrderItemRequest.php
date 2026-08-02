<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Admin\Orders;

use Illuminate\Foundation\Http\FormRequest;

class CreateOrderItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'completedAt' => ['prohibited'],
            'serviceId' => ['required', 'integer', 'min:1'],
            'quantity' => ['required', 'integer', 'min:1'],
            'selectedOptions' => ['sometimes', 'array'],
            'selectedOptions.*.pricingOptionId' => ['required_with:selectedOptions', 'integer', 'min:1'],
            'selectedOptions.*.valueIds' => ['required_with:selectedOptions', 'array'],
            'selectedOptions.*.valueIds.*' => ['integer', 'min:1'],
            'answers' => ['sometimes', 'array'],
            'answers.*.orderFieldId' => ['required_with:answers', 'integer', 'min:1'],
            'answers.*.answer' => ['required_with:answers', 'string', 'min:1', 'max:2000'],
            'itemNote' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('itemNote')) {
            $this->merge([
                'itemNote' => $this->normalizeOptionalString($this->input('itemNote')),
            ]);
        }

        $answers = $this->input('answers');

        if (is_array($answers)) {
            foreach ($answers as $index => $answer) {
                if (is_array($answer)) {
                    $answers[$index]['answer'] = $this->normalizeOptionalString($answer['answer'] ?? null);
                }
            }

            $this->merge([
                'answers' => $answers,
            ]);
        }
    }

    private function normalizeOptionalString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalized = trim((string) $value);
        $normalized = preg_replace('/\s+/u', ' ', $normalized) ?? $normalized;

        return $normalized === '' ? null : $normalized;
    }
}
