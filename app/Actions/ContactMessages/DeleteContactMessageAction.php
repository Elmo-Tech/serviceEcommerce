<?php

declare(strict_types=1);

namespace App\Actions\ContactMessages;

use App\Enums\HttpStatusCode;
use App\Exceptions\ApiBusinessException;
use App\Models\ContactMessage;

class DeleteContactMessageAction
{
    public function execute(int $contactMessageId): void
    {
        $contactMessage = ContactMessage::query()->find($contactMessageId);

        if (! $contactMessage instanceof ContactMessage) {
            throw new ApiBusinessException(
                'contact_messages.not_found',
                'CONTACT_MESSAGE_NOT_FOUND',
                HttpStatusCode::NOT_FOUND,
            );
        }

        $contactMessage->delete();
    }
}
