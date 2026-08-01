<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin\Services;

use App\Actions\Services\DeleteServiceMediaAction;
use App\Actions\Services\SetServiceMainMediaAction;
use App\Actions\Services\UploadServiceMediaAction;
use App\Enums\HttpStatusCode;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\Services\SetServiceMainMediaRequest;
use App\Http\Requests\Api\V1\Admin\Services\UpdateServiceMediaAltRequest;
use App\Http\Requests\Api\V1\Admin\Services\UploadServiceMediaRequest;
use App\Http\Resources\Api\V1\Admin\Services\ServiceMediaResource;
use App\Queries\Services\ServiceMediaIndexQuery;
use App\Services\Services\ServiceLookupService;
use App\Support\Api\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ServiceMediaController extends Controller
{
    public function __construct(
        private readonly ServiceLookupService $serviceLookupService,
        private readonly ServiceMediaIndexQuery $serviceMediaIndexQuery,
        private readonly UploadServiceMediaAction $uploadServiceMediaAction,
        private readonly SetServiceMainMediaAction $setServiceMainMediaAction,
        private readonly DeleteServiceMediaAction $deleteServiceMediaAction,
    ) {}

    public function index(Request $request, int|string $service): JsonResponse
    {
        $resolvedService = $this->serviceLookupService->findOrFail((int) $service, true);

        return ApiResponse::withAuthenticationHeaders(
            ApiResponse::success(__('services.retrieved'), ServiceMediaResource::collection($this->serviceMediaIndexQuery->get($resolvedService))->resolve($request)),
        );
    }

    public function store(UploadServiceMediaRequest $request, int|string $service): JsonResponse
    {
        $resolvedService = $this->serviceLookupService->findOrFail((int) $service, true);
        $media = $this->uploadServiceMediaAction->execute($resolvedService, $request->validated('media'));

        return ApiResponse::withAuthenticationHeaders(
            ApiResponse::success(__('service_media.uploaded'), ServiceMediaResource::collection($media)->resolve($request), HttpStatusCode::CREATED),
        );
    }

    public function update(UpdateServiceMediaAltRequest $request, int|string $service, int|string $media): JsonResponse
    {
        $resolvedService = $this->serviceLookupService->findOrFail((int) $service, true);
        $resolvedMedia = $resolvedService->media()->findOrFail((int) $media);

        $resolvedMedia->forceFill([
            'alt_text_ar' => $request->input('altAr'),
            'alt_text_en' => $request->input('altEn'),
        ])->save();

        return ApiResponse::withAuthenticationHeaders(ApiResponse::success(__('service_media.updated'), (new ServiceMediaResource($resolvedMedia))->resolve($request)));
    }

    public function destroy(int|string $service, int|string $media): JsonResponse
    {
        $resolvedService = $this->serviceLookupService->findOrFail((int) $service, true);
        $resolvedMedia = $resolvedService->media()->findOrFail((int) $media);
        $this->deleteServiceMediaAction->execute($resolvedService, $resolvedMedia);

        return ApiResponse::withAuthenticationHeaders(ApiResponse::success(__('service_media.deleted'), null));
    }

    public function setAsMain(SetServiceMainMediaRequest $request, int|string $service, int|string $media): JsonResponse
    {
        $resolvedService = $this->serviceLookupService->findOrFail((int) $service, true);
        $resolvedMedia = $resolvedService->media()->findOrFail((int) $media);
        $updatedMedia = $this->setServiceMainMediaAction->execute($resolvedService, $resolvedMedia);

        return ApiResponse::withAuthenticationHeaders(ApiResponse::success(__('service_media.set_main'), (new ServiceMediaResource($updatedMedia))->resolve($request)));
    }
}
