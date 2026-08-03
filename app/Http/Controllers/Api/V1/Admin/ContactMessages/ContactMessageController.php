<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin\ContactMessages;

use App\Actions\ContactMessages\DeleteContactMessageAction;
use App\Actions\ContactMessages\UpdateContactMessageStatusAction;
use App\Enums\HttpStatusCode;
use App\Exceptions\ApiBusinessException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\ContactMessages\IndexContactMessagesRequest;
use App\Http\Requests\Api\V1\Admin\ContactMessages\UpdateContactMessageRequest;
use App\Http\Resources\Api\V1\Admin\ContactMessages\AdminContactMessageIndexResource;
use App\Http\Resources\Api\V1\Admin\ContactMessages\AdminContactMessageResource;
use App\Models\ContactMessage;
use App\Queries\ContactMessages\AdminContactMessageIndexQuery;
use App\Support\Api\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ContactMessageController extends Controller
{
    public function __construct(
        private readonly AdminContactMessageIndexQuery $indexQuery,
        private readonly UpdateContactMessageStatusAction $updateAction,
        private readonly DeleteContactMessageAction $deleteAction,
    ) {}

    public function index(IndexContactMessagesRequest $request): JsonResponse
    {
        $messages = $this->indexQuery->paginate($request->filters());

        return ApiResponse::withAuthenticationHeaders(response()->json([
            'success' => true,
            'message' => __('contact_messages.listed'),
            'data' => AdminContactMessageIndexResource::collection(collect($messages->items()))->resolve($request),
            'meta' => [
                'currentPage' => $messages->currentPage(),
                'lastPage' => $messages->lastPage(),
                'perPage' => $messages->perPage(),
                'total' => $messages->total(),
            ],
        ], HttpStatusCode::OK->value));
    }

    public function show(Request $request, int|string $contactMessage): JsonResponse
    {
        $resource = $this->findOrFail((int) $contactMessage);

        return ApiResponse::withAuthenticationHeaders(ApiResponse::success(
            __('contact_messages.retrieved'),
            (new AdminContactMessageResource($resource))->resolve($request),
        ));
    }

    public function update(UpdateContactMessageRequest $request, int|string $contactMessage): JsonResponse
    {
        $resource = $this->updateAction->execute((int) $contactMessage, $request->status());

        return ApiResponse::withAuthenticationHeaders(ApiResponse::success(
            __('contact_messages.updated'),
            (new AdminContactMessageResource($resource))->resolve($request),
        ));
    }

    public function destroy(int|string $contactMessage): JsonResponse
    {
        $this->deleteAction->execute((int) $contactMessage);

        return ApiResponse::withAuthenticationHeaders(ApiResponse::success(
            __('contact_messages.deleted'),
            null,
        ));
    }

    private function findOrFail(int $id): ContactMessage
    {
        $contactMessage = ContactMessage::query()->find($id);

        if (! $contactMessage instanceof ContactMessage) {
            throw new ApiBusinessException(
                'contact_messages.not_found',
                'CONTACT_MESSAGE_NOT_FOUND',
                HttpStatusCode::NOT_FOUND,
            );
        }

        return $contactMessage;
    }
}
