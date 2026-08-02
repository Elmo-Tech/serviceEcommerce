<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Admin\Orders;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateOrderItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'quantity' => ['sometimes', 'integer', 'min:1'],
            'selectedOptions' => ['sometimes', 'array'],
            'selectedOptions.*.pricingOptionId' => ['required_with:selectedOptions', 'integer', 'min:1'],
            'selectedOptions.*.valueIds' => ['required_with:selectedOptions', 'array'],
            'selectedOptions.*.valueIds.*' => ['integer', 'min:1'],
            'answers' => ['sometimes', 'array'],
            'answers.*.orderItemAnswerId' => ['required_with:answers', 'integer', 'min:1'],
            'answers.*.answer' => ['required_with:answers', 'string', 'min:1', 'max:2000'],
            'itemNote' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $this->guardUnexpectedKeys($validator);

                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                if ($this->all() === []) {
                    $validator->errors()->add('payload', __('validation.invalid_payload'));
                }
            },
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

    private function guardUnexpectedKeys(Validator $validator): void
    {
        if (array_diff(array_keys($this->all()), ['quantity', 'selectedOptions', 'answers', 'itemNote']) !== []) {
            $validator->errors()->add('payload', __('validation.invalid_payload'));
        }

        foreach ((array) $this->input('selectedOptions', []) as $selection) {
            if (is_array($selection) && array_diff(array_keys($selection), ['pricingOptionId', 'valueIds']) !== []) {
                $validator->errors()->add('payload', __('validation.invalid_payload'));
            }
        }

        foreach ((array) $this->input('answers', []) as $answer) {
            if (is_array($answer) && array_diff(array_keys($answer), ['orderItemAnswerId', 'answer']) !== []) {
                $validator->errors()->add('payload', __('validation.invalid_payload'));
            }
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
