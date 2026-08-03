<?php

declare(strict_types=1);

namespace App\Actions\ContactMessages;

use App\Enums\ContactMessages\ContactMessageStatus;
use App\Models\ContactMessage;

class CreateContactMessageAction
{
    /**
     * @param  array{name:string,email:?string,phone:string,subject:string,message:string}  $payload
     */
    public function execute(array $payload): ContactMessage
    {
        /** @var ContactMessage $contactMessage */
        $contactMessage = ContactMessage::query()->create([
            'name' => $payload['name'],
            'email' => $payload['email'],
            'phone' => $payload['phone'],
            'subject' => $payload['subject'],
            'message' => $payload['message'],
            'status' => ContactMessageStatus::NEW,
        ]);

        return $contactMessage;
    }
}
