<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Public\Faqs;

use App\Enums\HttpStatusCode;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Public\Faqs\ListPublicFaqsRequest;
use App\Http\Resources\Api\V1\Public\Faqs\PublicFaqResource;
use App\Models\Faq;
use App\Support\Api\ApiResponse;
use Illuminate\Http\JsonResponse;

class FaqController extends Controller
{
    public function index(ListPublicFaqsRequest $request): JsonResponse
    {
        $filters = $request->filters();

        $faqs = Faq::query()
            ->active()
            ->ordered()
            ->paginate($filters['perPage'], ['*'], 'page', $filters['page'])
            ->withQueryString();

        return ApiResponse::withAuthenticationHeaders(response()->json([
            'success' => true,
            'message' => __('faqs.listed'),
            'data' => PublicFaqResource::collection(collect($faqs->items()))->resolve($request),
            'meta' => [
                'currentPage' => $faqs->currentPage(),
                'lastPage' => $faqs->lastPage(),
                'perPage' => $faqs->perPage(),
                'total' => $faqs->total(),
            ],
        ], HttpStatusCode::OK->value));
    }
}
