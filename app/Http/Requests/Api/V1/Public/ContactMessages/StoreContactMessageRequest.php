<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Public\ContactMessages;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreContactMessageRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $normalized = $this->all();

        foreach (['name', 'email', 'phone', 'subject', 'message'] as $field) {
            if (array_key_exists($field, $normalized) && is_string($normalized[$field])) {
                $normalized[$field] = trim($normalized[$field]);
            }
        }

        if (array_key_exists('email', $normalized) && $normalized['email'] === '') {
            $normalized['email'] = null;
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
            'name' => ['required', 'string', 'min:2', 'max:150', 'not_regex:/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u'],
            'email' => ['sometimes', 'nullable', 'email:rfc', 'max:254'],
            'phone' => ['required', 'string', 'min:3', 'max:30', 'regex:/^[0-9+\-\s()]+$/', 'not_regex:/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F<>]/u'],
            'subject' => ['required', 'string', 'min:3', 'max:200', 'not_regex:/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u'],
            'message' => ['required', 'string', 'min:10', 'max:5000', 'not_regex:/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $allowed = ['name', 'email', 'phone', 'subject', 'message'];

                $unexpected = array_diff(array_keys($this->all()), $allowed);

                if ($unexpected !== []) {
                    $validator->errors()->add('payload', __('validation.invalid_payload'));
                }
            },
        ];
    }

    /**
     * @return array{name:string,email:?string,phone:string,subject:string,message:string}
     */
    public function payload(): array
    {
        return [
            'name' => (string) $this->validated('name'),
            'email' => $this->validated('email'),
            'phone' => (string) $this->validated('phone'),
            'subject' => (string) $this->validated('subject'),
            'message' => (string) $this->validated('message'),
        ];
    }
}
