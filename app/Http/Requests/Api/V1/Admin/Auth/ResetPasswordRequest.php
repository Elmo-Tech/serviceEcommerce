<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Admin\Auth;

use App\Rules\Auth\AdminPasswordRules;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class ResetPasswordRequest extends FormRequest
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
            'email' => ['required', 'string', 'email:rfc', 'max:255'],
            'resetToken' => ['required', 'string', 'min:1'],
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
                $allowedKeys = ['email', 'password', 'passwordConfirmation', 'resetToken'];
                $unexpectedKeys = array_diff(array_keys($this->all()), $allowedKeys);

                if ($unexpectedKeys !== []) {
                    $validator->errors()->add('payload', __('validation.invalid_payload'));
                }
            },
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('email')) {
            $this->merge([
                'email' => $this->normalizedEmail(),
            ]);
        }
    }

    public function normalizedEmail(): string
    {
        return mb_strtolower(trim((string) $this->input('email')));
    }
}
