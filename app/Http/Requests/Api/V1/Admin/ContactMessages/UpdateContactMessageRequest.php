<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Admin\ContactMessages;

use App\Enums\ContactMessages\ContactMessageStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateContactMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', 'string', Rule::in(ContactMessageStatus::keys())],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $allowed = ['status'];

                $unexpected = array_diff(array_keys($this->all()), $allowed);

                if ($unexpected !== []) {
                    $validator->errors()->add('payload', __('validation.invalid_payload'));
                }

                if ($this->all() === []) {
                    $validator->errors()->add('payload', __('validation.invalid_payload'));
                }
            },
        ];
    }

    public function status(): ContactMessageStatus
    {
        return ContactMessageStatus::fromKey((string) $this->validated('status')) ?? ContactMessageStatus::NEW;
    }
}
