<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin\HeroSlides;

use App\Actions\HeroSlides\CreateHeroSlideAction;
use App\Actions\HeroSlides\DeleteHeroSlideAction;
use App\Actions\HeroSlides\UpdateHeroSlideAction;
use App\Enums\HttpStatusCode;
use App\Exceptions\ApiBusinessException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\HeroSlides\ListHeroSlidesRequest;
use App\Http\Requests\Api\V1\Admin\HeroSlides\StoreHeroSlideRequest;
use App\Http\Requests\Api\V1\Admin\HeroSlides\UpdateHeroSlideRequest;
use App\Http\Resources\Api\V1\Admin\HeroSlides\AdminHeroSlideResource;
use App\Models\HeroSlide;
use App\Queries\HeroSlides\AdminHeroSlideIndexQuery;
use App\Support\Api\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HeroSlideController extends Controller
{
    public function __construct(
        private readonly AdminHeroSlideIndexQuery $indexQuery,
        private readonly CreateHeroSlideAction $createAction,
        private readonly UpdateHeroSlideAction $updateAction,
        private readonly DeleteHeroSlideAction $deleteAction,
    ) {}

    public function index(ListHeroSlidesRequest $request): JsonResponse
    {
        $slides = $this->indexQuery->paginate($request->filters());

        return ApiResponse::withAuthenticationHeaders(response()->json([
            'success' => true,
            'message' => __('hero_slides.listed'),
            'data' => AdminHeroSlideResource::collection(collect($slides->items()))->resolve($request),
            'meta' => [
                'currentPage' => $slides->currentPage(),
                'lastPage' => $slides->lastPage(),
                'perPage' => $slides->perPage(),
                'total' => $slides->total(),
            ],
        ], HttpStatusCode::OK->value));
    }

    public function store(StoreHeroSlideRequest $request): JsonResponse
    {
        $slide = $this->createAction->execute($request->payload());

        return ApiResponse::withAuthenticationHeaders(ApiResponse::success(
            __('hero_slides.created'),
            (new AdminHeroSlideResource($slide))->resolve($request),
            HttpStatusCode::CREATED,
        ));
    }

    public function show(Request $request, int|string $heroSlide): JsonResponse
    {
        $slide = $this->findOrFail((int) $heroSlide);

        return ApiResponse::withAuthenticationHeaders(ApiResponse::success(
            __('hero_slides.retrieved'),
            (new AdminHeroSlideResource($slide))->resolve($request),
        ));
    }

    public function update(UpdateHeroSlideRequest $request, int|string $heroSlide): JsonResponse
    {
        $slide = $this->updateAction->execute((int) $heroSlide, $request->payload());

        return ApiResponse::withAuthenticationHeaders(ApiResponse::success(
            __('hero_slides.updated'),
            (new AdminHeroSlideResource($slide))->resolve($request),
        ));
    }

    public function destroy(int|string $heroSlide): JsonResponse
    {
        $this->deleteAction->execute((int) $heroSlide);

        return ApiResponse::withAuthenticationHeaders(ApiResponse::success(
            __('hero_slides.deleted'),
            null,
        ));
    }

    private function findOrFail(int $id): HeroSlide
    {
        $slide = HeroSlide::query()->find($id);

        if (! $slide instanceof HeroSlide) {
            throw new ApiBusinessException(
                'hero_slides.not_found',
                'HERO_SLIDE_NOT_FOUND',
                HttpStatusCode::NOT_FOUND,
            );
        }

        return $slide;
    }
}
