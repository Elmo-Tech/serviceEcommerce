<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Admin\Orders;

use App\Services\Orders\OrderAttachmentStore;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class CreateAdminOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'customerId' => ['sometimes', 'integer', 'min:1'],
            'customer' => ['sometimes', 'array'],
            'customer.name' => ['required_with:customer', 'string', 'min:1', 'max:150'],
            'customer.email' => ['sometimes', 'nullable', 'string', 'email:rfc', 'max:255'],
            'customer.phone' => ['required_with:customer', 'string', 'min:1', 'max:30'],
            'customerAddressId' => ['sometimes', 'integer', 'min:1'],
            'address' => ['sometimes', 'nullable', 'array'],
            'address.province' => ['required_with:address', 'string', 'min:1', 'max:150'],
            'address.city' => ['required_with:address', 'string', 'min:1', 'max:150'],
            'address.address' => ['required_with:address', 'string', 'min:1', 'max:500'],
            'orderPlace' => ['required', 'integer', Rule::in([0, 1])],
            'customerNote' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'adminNote' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'discountType' => ['sometimes', 'nullable', 'integer', Rule::in([0, 1])],
            'discountValue' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'discountReason' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'items' => ['required', 'array', 'min:1', 'max:50'],
            'items.*.serviceId' => ['required', 'integer', 'min:1'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.selectedOptions' => ['sometimes', 'array'],
            'items.*.selectedOptions.*.pricingOptionId' => ['required_with:items.*.selectedOptions', 'integer', 'min:1'],
            'items.*.selectedOptions.*.valueIds' => ['required_with:items.*.selectedOptions', 'array'],
            'items.*.selectedOptions.*.valueIds.*' => ['integer', 'min:1'],
            'items.*.answers' => ['sometimes', 'array'],
            'items.*.answers.*.orderFieldId' => ['required_with:items.*.answers', 'integer', 'min:1'],
            'items.*.answers.*.answer' => ['required_with:items.*.answers', 'string', 'min:1', 'max:2000'],
            'items.*.itemNote' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'items.*.attachments' => ['sometimes', 'array', 'max:3'],
            'items.*.attachments.*' => [
                'file',
                'max:10240',
                'mimetypes:image/png,image/jpeg,image/webp,application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            ],
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

                $hasCustomerId = $this->filled('customerId');
                $hasCustomer = is_array($this->input('customer'));

                if ($hasCustomerId === $hasCustomer) {
                    $validator->errors()->add('customer', __('validation.invalid_payload'));
                }

                if ($this->filled('customerAddressId') && is_array($this->input('address'))) {
                    $validator->errors()->add('address', __('validation.invalid_payload'));
                }

                $hasDiscountType = $this->input('discountType') !== null && $this->input('discountType') !== '';
                $hasDiscountValue = $this->input('discountValue') !== null && $this->input('discountValue') !== '';
                $hasDiscountReason = $this->normalizeOptionalString($this->input('discountReason')) !== null;

                if ($hasDiscountType) {
                    if (! $hasDiscountValue || ! $hasDiscountReason) {
                        $validator->errors()->add('discount', __('validation.invalid_payload'));
                    }
                }

                if (! $hasDiscountType && ($hasDiscountValue || $hasDiscountReason)) {
                    $validator->errors()->add('discount', __('validation.invalid_payload'));
                }

                app(OrderAttachmentStore::class)->ensureCreateRequestAggregateLimits(
                    $this->allAttachmentFiles(),
                );
            },
        ];
    }

    protected function prepareForValidation(): void
    {
        $customer = $this->input('customer');
        $address = $this->input('address');
        $items = $this->input('items');

        if (is_array($customer)) {
            $customer['name'] = $this->normalizeOptionalString($customer['name'] ?? null);
            $customer['email'] = $this->normalizeOptionalEmail($customer['email'] ?? null);
            $customer['phone'] = $this->normalizeOptionalString($customer['phone'] ?? null);
        }

        if (is_array($address)) {
            $address['province'] = $this->normalizeOptionalString($address['province'] ?? null);
            $address['city'] = $this->normalizeOptionalString($address['city'] ?? null);
            $address['address'] = $this->normalizeOptionalString($address['address'] ?? null);
        }

        if (is_array($items)) {
            foreach ($items as $index => $item) {
                if (! is_array($item)) {
                    continue;
                }

                if (is_array($item['answers'] ?? null)) {
                    foreach ($item['answers'] as $answerIndex => $answer) {
                        if (is_array($answer)) {
                            $items[$index]['answers'][$answerIndex]['answer'] = $this->normalizeOptionalString($answer['answer'] ?? null);
                        }
                    }
                }

                if (array_key_exists('itemNote', $item)) {
                    $items[$index]['itemNote'] = $this->normalizeOptionalString($item['itemNote']);
                }
            }
        }

        $payload = [
            'customerNote' => $this->normalizeOptionalString($this->input('customerNote')),
            'adminNote' => $this->normalizeOptionalString($this->input('adminNote')),
            'discountReason' => $this->normalizeOptionalString($this->input('discountReason')),
            'items' => $items,
        ];

        if (is_array($customer)) {
            $payload['customer'] = $customer;
        }

        if (is_array($address)) {
            $payload['address'] = $address;
        }

        $this->merge($payload);
    }

    public function payload(): array
    {
        return $this->validated();
    }

    private function guardUnexpectedKeys(Validator $validator): void
    {
        $allowedTopLevel = ['customerId', 'customer', 'customerAddressId', 'address', 'orderPlace', 'customerNote', 'adminNote', 'discountType', 'discountValue', 'discountReason', 'items'];
        $unexpectedTopLevel = array_diff(array_keys($this->all()), $allowedTopLevel);

        if ($unexpectedTopLevel !== []) {
            $validator->errors()->add('payload', __('validation.invalid_payload'));
        }

        $customer = $this->input('customer');
        if (is_array($customer) && array_diff(array_keys($customer), ['name', 'email', 'phone']) !== []) {
            $validator->errors()->add('payload', __('validation.invalid_payload'));
        }

        $address = $this->input('address');
        if (is_array($address) && array_diff(array_keys($address), ['province', 'city', 'address']) !== []) {
            $validator->errors()->add('payload', __('validation.invalid_payload'));
        }

        foreach ((array) $this->input('items', []) as $item) {
            if (! is_array($item)) {
                continue;
            }

            if (array_diff(array_keys($item), ['serviceId', 'quantity', 'selectedOptions', 'answers', 'itemNote', 'attachments']) !== []) {
                $validator->errors()->add('payload', __('validation.invalid_payload'));
            }

            foreach ((array) ($item['selectedOptions'] ?? []) as $selection) {
                if (is_array($selection) && array_diff(array_keys($selection), ['pricingOptionId', 'valueIds']) !== []) {
                    $validator->errors()->add('payload', __('validation.invalid_payload'));
                }
            }

            foreach ((array) ($item['answers'] ?? []) as $answer) {
                if (is_array($answer) && array_diff(array_keys($answer), ['orderFieldId', 'answer']) !== []) {
                    $validator->errors()->add('payload', __('validation.invalid_payload'));
                }
            }
        }
    }

    /**
     * @return list<UploadedFile>
     */
    private function allAttachmentFiles(): array
    {
        $files = [];

        foreach ((array) $this->file('items', []) as $itemFiles) {
            if (! is_array($itemFiles) || ! is_array($itemFiles['attachments'] ?? null)) {
                continue;
            }

            foreach ($itemFiles['attachments'] as $attachment) {
                if ($attachment instanceof UploadedFile) {
                    $files[] = $attachment;
                }
            }
        }

        return $files;
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

    private function normalizeOptionalEmail(mixed $value): ?string
    {
        $normalized = $this->normalizeOptionalString($value);

        return $normalized === null ? null : mb_strtolower($normalized);
    }
}
