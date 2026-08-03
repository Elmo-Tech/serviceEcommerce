<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin\Faqs;

use App\Actions\Faqs\CreateFaqAction;
use App\Actions\Faqs\DeleteFaqAction;
use App\Actions\Faqs\UpdateFaqAction;
use App\Enums\HttpStatusCode;
use App\Exceptions\ApiBusinessException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\Faqs\ListFaqsRequest;
use App\Http\Requests\Api\V1\Admin\Faqs\StoreFaqRequest;
use App\Http\Requests\Api\V1\Admin\Faqs\UpdateFaqRequest;
use App\Http\Resources\Api\V1\Admin\Faqs\AdminFaqResource;
use App\Models\Faq;
use App\Queries\Faqs\AdminFaqIndexQuery;
use App\Support\Api\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FaqController extends Controller
{
    public function __construct(
        private readonly AdminFaqIndexQuery $indexQuery,
        private readonly CreateFaqAction $createAction,
        private readonly UpdateFaqAction $updateAction,
        private readonly DeleteFaqAction $deleteAction,
    ) {}

    public function index(ListFaqsRequest $request): JsonResponse
    {
        $faqs = $this->indexQuery->paginate($request->filters());

        return ApiResponse::withAuthenticationHeaders(response()->json([
            'success' => true,
            'message' => __('faqs.listed'),
            'data' => AdminFaqResource::collection(collect($faqs->items()))->resolve($request),
            'meta' => [
                'currentPage' => $faqs->currentPage(),
                'lastPage' => $faqs->lastPage(),
                'perPage' => $faqs->perPage(),
                'total' => $faqs->total(),
            ],
        ], HttpStatusCode::OK->value));
    }

    public function store(StoreFaqRequest $request): JsonResponse
    {
        $faq = $this->createAction->execute($request->payload());

        return ApiResponse::withAuthenticationHeaders(ApiResponse::success(
            __('faqs.created'),
            (new AdminFaqResource($faq))->resolve($request),
            HttpStatusCode::CREATED,
        ));
    }

    public function show(Request $request, int|string $faq): JsonResponse
    {
        $resource = $this->findOrFail((int) $faq);

        return ApiResponse::withAuthenticationHeaders(ApiResponse::success(
            __('faqs.retrieved'),
            (new AdminFaqResource($resource))->resolve($request),
        ));
    }

    public function update(UpdateFaqRequest $request, int|string $faq): JsonResponse
    {
        $resource = $this->updateAction->execute((int) $faq, $request->payload());

        return ApiResponse::withAuthenticationHeaders(ApiResponse::success(
            __('faqs.updated'),
            (new AdminFaqResource($resource))->resolve($request),
        ));
    }

    public function destroy(int|string $faq): JsonResponse
    {
        $this->deleteAction->execute((int) $faq);

        return ApiResponse::withAuthenticationHeaders(ApiResponse::success(
            __('faqs.deleted'),
            null,
        ));
    }

    private function findOrFail(int $id): Faq
    {
        $faq = Faq::query()->find($id);

        if (! $faq instanceof Faq) {
            throw new ApiBusinessException(
                'faqs.not_found',
                'FAQ_NOT_FOUND',
                HttpStatusCode::NOT_FOUND,
            );
        }

        return $faq;
    }
}
