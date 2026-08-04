<?php

declare(strict_types=1);

namespace App\Services\Orders;

use App\Enums\HttpStatusCode;
use App\Exceptions\ApiBusinessException;
use App\Models\Service;
use Illuminate\Http\UploadedFile;

class RequiredServiceAttachmentValidator
{
    /** @param array<int, mixed> $attachments */
    public function validate(Service $service, array $attachments): void
    {
        if (! $service->is_attachment_required) {
            return;
        }

        foreach ($attachments as $attachment) {
            if ($attachment instanceof UploadedFile && $attachment->isValid()) {
                return;
            }
        }

        throw new ApiBusinessException(
            'orders.errors.required_service_attachment_missing',
            'REQUIRED_SERVICE_ATTACHMENT_MISSING',
            HttpStatusCode::UNPROCESSABLE_ENTITY,
        );
    }
}
