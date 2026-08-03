<?php

declare(strict_types=1);

namespace App\Actions\ContactMessages;

use App\Enums\ContactMessages\ContactMessageStatus;
use App\Enums\HttpStatusCode;
use App\Exceptions\ApiBusinessException;
use App\Models\ContactMessage;

class UpdateContactMessageStatusAction
{
    public function execute(int $contactMessageId, ContactMessageStatus $status): ContactMessage
    {
        $contactMessage = ContactMessage::query()->find($contactMessageId);

        if (! $contactMessage instanceof ContactMessage) {
            throw new ApiBusinessException(
                'contact_messages.not_found',
                'CONTACT_MESSAGE_NOT_FOUND',
                HttpStatusCode::NOT_FOUND,
            );
        }

        $contactMessage->forceFill(['status' => $status])->save();

        /** @var ContactMessage $freshContactMessage */
        $freshContactMessage = $contactMessage->fresh();

        return $freshContactMessage;
    }
}
