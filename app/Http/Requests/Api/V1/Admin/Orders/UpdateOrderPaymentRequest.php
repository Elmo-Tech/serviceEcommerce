<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Admin\Orders;

use Illuminate\Foundation\Http\FormRequest;

class UpdateOrderPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'completedAt' => ['prohibited'],
            'paidAmount' => ['required', 'numeric', 'min:0'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('paidAmount')) {
            $this->merge([
                'paidAmount' => number_format((float) $this->input('paidAmount'), 2, '.', ''),
            ]);
        }
    }
}
