<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Admin\Orders;

use App\Services\Orders\OrderAttachmentStore;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UploadOrderItemAttachmentsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'files' => ['required', 'array', 'min:1', 'max:3'],
            'files.*' => [
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
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                app(OrderAttachmentStore::class)->ensureStandaloneUploadLimits(0, $this->uploadedFiles());
            },
        ];
    }

    /**
     * @return list<\Illuminate\Http\UploadedFile>
     */
    public function uploadedFiles(): array
    {
        return array_values(array_filter(
            (array) $this->file('files', []),
            static fn ($file) => $file instanceof \Illuminate\Http\UploadedFile,
        ));
    }
}
