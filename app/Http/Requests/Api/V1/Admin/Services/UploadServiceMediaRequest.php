<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Admin\Services;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UploadServiceMediaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $media = $this->input('media');

        if (! is_array($media)) {
            return;
        }

        $normalizedMedia = array_map(function (mixed $item): mixed {
            if (! is_array($item) || ! array_key_exists('isMain', $item)) {
                return $item;
            }

            if ($item['isMain'] === 'true') {
                $item['isMain'] = true;
            }

            if ($item['isMain'] === 'false') {
                $item['isMain'] = false;
            }

            return $item;
        }, $media);

        $this->merge([
            'media' => $normalizedMedia,
        ]);
    }

    public function rules(): array
    {
        return [
            'media' => ['required', 'array', 'min:1', 'max:11'],
            'media.*.file' => ['required', 'file', 'max:25600', 'mimetypes:image/jpeg,image/png,image/webp,video/mp4,video/quicktime'],
            'media.*.type' => ['required', 'integer', Rule::in([0, 1])],
            'media.*.isMain' => ['sometimes', 'boolean'],
            'media.*.altAr' => ['sometimes', 'nullable', 'string', 'max:255', 'required_with:media.*.altEn'],
            'media.*.altEn' => ['sometimes', 'nullable', 'string', 'max:255', 'required_with:media.*.altAr'],
        ];
    }
}
