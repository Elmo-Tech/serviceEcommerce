<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Admin\Auth;

use App\Rules\Auth\AdminPasswordRules;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class ChangePasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'currentPassword' => ['required', 'string'],
            'password' => AdminPasswordRules::ruleSet(),
            'passwordConfirmation' => AdminPasswordRules::confirmationRule('password'),
        ];
    }

    /**
     * @return array<int, Closure(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $allowedKeys = ['currentPassword', 'password', 'passwordConfirmation'];
                $unexpectedKeys = array_diff(array_keys($this->all()), $allowedKeys);

                if ($unexpectedKeys !== []) {
                    $validator->errors()->add('payload', __('validation.invalid_payload'));
                }

                AdminPasswordRules::ensureDifferentFromCurrent(
                    $validator,
                    $this->input('currentPassword'),
                    $this->input('password'),
                );
            },
        ];
    }
}
