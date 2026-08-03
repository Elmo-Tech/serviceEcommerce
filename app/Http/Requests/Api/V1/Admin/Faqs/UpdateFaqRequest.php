<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Admin\Faqs;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateFaqRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $normalized = $this->all();

        foreach (['questionAr', 'questionEn', 'answerAr', 'answerEn'] as $field) {
            if (array_key_exists($field, $normalized) && is_string($normalized[$field])) {
                $normalized[$field] = trim($normalized[$field]);
            }
        }

        if (array_key_exists('isActive', $normalized)) {
            if (is_bool($normalized['isActive'])) {
                $normalized['isActive'] = $normalized['isActive'] ? '1' : '0';
            } elseif (is_int($normalized['isActive'])) {
                $normalized['isActive'] = (string) $normalized['isActive'];
            }
        }

        if (array_key_exists('position', $normalized) && is_int($normalized['position'])) {
            $normalized['position'] = (string) $normalized['position'];
        }

        $this->merge($normalized);
    }

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'questionAr' => ['sometimes', 'required', 'string', 'min:1', 'max:500', 'not_regex:/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u'],
            'questionEn' => ['sometimes', 'required', 'string', 'min:1', 'max:500', 'not_regex:/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u'],
            'answerAr' => ['sometimes', 'required', 'string', 'min:1', 'max:5000', 'not_regex:/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u'],
            'answerEn' => ['sometimes', 'required', 'string', 'min:1', 'max:5000', 'not_regex:/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u'],
            'isActive' => ['sometimes', 'required', 'string', 'regex:/^[01]$/'],
            'position' => ['sometimes', 'required', 'string', 'regex:/^[1-9][0-9]*$/'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $allowed = [
                    'questionAr',
                    'questionEn',
                    'answerAr',
                    'answerEn',
                    'isActive',
                    'position',
                ];

                $unexpected = array_diff(array_keys($this->all()), $allowed);

                if ($unexpected !== []) {
                    $validator->errors()->add('payload', __('validation.invalid_payload'));
                }

                if (array_intersect(array_keys($this->all()), $allowed) === []) {
                    $validator->errors()->add('payload', __('validation.invalid_payload'));
                }
            },
        ];
    }

    /**
     * @return array{questionAr?:string,questionEn?:string,answerAr?:string,answerEn?:string,isActive?:int,position?:int}
     */
    public function payload(): array
    {
        $validated = $this->validated();
        $payload = [];

        foreach (['questionAr', 'questionEn', 'answerAr', 'answerEn'] as $field) {
            if (array_key_exists($field, $validated)) {
                $payload[$field] = (string) $validated[$field];
            }
        }

        if (array_key_exists('isActive', $validated)) {
            $payload['isActive'] = (int) $validated['isActive'];
        }

        if (array_key_exists('position', $validated)) {
            $payload['position'] = (int) $validated['position'];
        }

        return $payload;
    }
}
