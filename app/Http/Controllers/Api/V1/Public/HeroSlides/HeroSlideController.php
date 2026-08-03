<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Public\HeroSlides;

use App\Enums\HttpStatusCode;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\Public\HeroSlides\PublicHeroSlideResource;
use App\Models\HeroSlide;
use App\Support\Api\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HeroSlideController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $slides = HeroSlide::query()
            ->active()
            ->ordered()
            ->limit(10)
            ->get();

        return ApiResponse::withAuthenticationHeaders(
            ApiResponse::success(
                __('hero_slides.listed'),
                PublicHeroSlideResource::collection($slides)->resolve($request),
                HttpStatusCode::OK,
            ),
        );
    }
}
