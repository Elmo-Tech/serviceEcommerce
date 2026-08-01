<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Admin\Services;

use Illuminate\Foundation\Http\FormRequest;

class UpdateServiceMediaAltRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'altAr' => ['required', 'nullable', 'string', 'max:255', 'required_with:altEn'],
            'altEn' => ['required', 'nullable', 'string', 'max:255', 'required_with:altAr'],
        ];
    }
}
