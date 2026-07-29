<?php

declare(strict_types=1);

namespace App\Rules\Auth;

use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\Validator;

final class AdminPasswordRules
{
    public static function password(): Password
    {
        return Password::min(10)->mixedCase()->numbers()->symbols();
    }

    /**
     * @return array<int, Password|string>
     */
    public static function ruleSet(): array
    {
        return ['required', 'string', self::password()];
    }

    /**
     * @return array<int, string>
     */
    public static function confirmationRule(string $field = 'password'): array
    {
        return ['required', 'string', 'same:'.$field];
    }

    public static function ensureDifferentFromCurrent(
        Validator $validator,
        mixed $currentPassword,
        mixed $newPassword,
        string $errorField = 'password',
    ): void {
        if (! is_string($currentPassword) || ! is_string($newPassword)) {
            return;
        }

        if ($currentPassword === '' || $newPassword === '') {
            return;
        }

        if (hash_equals($currentPassword, $newPassword)) {
            $validator->errors()->add($errorField, __('validation.password_same_as_current'));
        }
    }
}
