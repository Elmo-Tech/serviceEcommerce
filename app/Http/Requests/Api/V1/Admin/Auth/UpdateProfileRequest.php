<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Admin\Auth;

use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateProfileRequest extends FormRequest
{
    private const MAX_AVATAR_BYTES = 2 * 1024 * 1024;

    /**
     * @var list<string>
     */
    private const ALLOWED_AVATAR_MIME_TYPES = [
        'image/jpeg',
        'image/png',
        'image/webp',
    ];

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
            '_method' => ['sometimes', 'string', Rule::in(['PATCH'])],
            'name' => ['sometimes', 'required', 'string', 'min:1', 'max:150'],
        ];
    }

    /**
     * @return array<int, Closure(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $allowedKeys = ['_method', 'avatar', 'name'];
                $unexpectedKeys = array_diff(array_keys($this->all()), $allowedKeys);

                if ($unexpectedKeys !== []) {
                    $validator->errors()->add('payload', __('validation.invalid_payload'));
                }

                if (! $this->has('avatar')) {
                    return;
                }

                if ($this->preservesCurrentAvatar()) {
                    return;
                }

                $avatar = $this->avatarUpload();

                if (! $avatar instanceof UploadedFile || ! $avatar->isValid()) {
                    $validator->errors()->add('avatar', __('validation.invalid_file_upload'));

                    return;
                }

                if (! in_array($avatar->getMimeType(), self::ALLOWED_AVATAR_MIME_TYPES, true)) {
                    $validator->errors()->add('avatar', __('validation.invalid_image_upload'));
                }

                if ($avatar->getSize() > self::MAX_AVATAR_BYTES) {
                    $validator->errors()->add('avatar', __('validation.file_too_large'));
                }
            },
        ];
    }

    public function avatarUpload(): ?UploadedFile
    {
        $avatar = $this->file('avatar');

        return $avatar instanceof UploadedFile ? $avatar : null;
    }

    public function preservesCurrentAvatar(): bool
    {
        if (! $this->has('avatar')) {
            return true;
        }

        return $this->input('avatar') === null || $this->input('avatar') === '';
    }
}
