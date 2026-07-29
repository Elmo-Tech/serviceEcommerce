<?php

declare(strict_types=1);

namespace App\Support\Api;

use App\Enums\HttpStatusCode;
use Illuminate\Http\JsonResponse;

class ApiResponse
{
    public static function success(
        string $message,
        mixed $data,
        HttpStatusCode $status = HttpStatusCode::OK,
    ): JsonResponse {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data,
        ], $status->value);
    }

    public static function error(
        string $message,
        string $code,
        mixed $errors = null,
        HttpStatusCode $status = HttpStatusCode::BAD_REQUEST,
    ): JsonResponse {
        return response()->json([
            'success' => false,
            'message' => $message,
            'code' => $code,
            'errors' => $errors,
        ], $status->value);
    }

    public static function withAuthenticationHeaders(
        JsonResponse $response,
        ?string $locale = null,
    ): JsonResponse {
        $resolvedLocale = $locale ?: app()->getLocale();

        $response->headers->set('Content-Language', $resolvedLocale);
        $response->headers->set('Cache-Control', 'no-store, private');
        $response->headers->set('Pragma', 'no-cache');

        $vary = $response->headers->get('Vary');
        $varyValues = array_filter(array_map('trim', explode(',', (string) $vary)));

        if (! in_array('Accept-Language', $varyValues, true)) {
            $varyValues[] = 'Accept-Language';
        }

        $response->headers->set('Vary', implode(', ', $varyValues));

        return $response;
    }
}
