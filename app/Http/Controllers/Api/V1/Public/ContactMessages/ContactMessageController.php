<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Public\ContactMessages;

use App\Actions\ContactMessages\CreateContactMessageAction;
use App\Enums\HttpStatusCode;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Public\ContactMessages\StoreContactMessageRequest;
use App\Support\Api\ApiResponse;
use Illuminate\Http\JsonResponse;

class ContactMessageController extends Controller
{
    public function __construct(
        private readonly CreateContactMessageAction $createAction,
    ) {}

    public function store(StoreContactMessageRequest $request): JsonResponse
    {
        $this->createAction->execute($request->payload());

        return ApiResponse::withAuthenticationHeaders(ApiResponse::success(
            __('contact_messages.created'),
            null,
            HttpStatusCode::CREATED,
        ));
    }
}
