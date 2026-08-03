<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\Http\SettingsMultipartPatchParser;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class ParseSettingsMultipartPatch
{
    public function __construct(private readonly SettingsMultipartPatchParser $parser) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->isMethod('PATCH')
            || ! str_starts_with(strtolower((string) $request->header('Content-Type')), 'multipart/form-data')) {
            return $next($request);
        }

        if ((int) $request->server('CONTENT_LENGTH', 0) > SettingsMultipartPatchParser::MAX_BODY_BYTES) {
            throw ValidationException::withMessages([
                'payload' => [__('validation.invalid_payload')],
            ]);
        }

        try {
            $parsed = $this->parser->parse(
                (string) $request->header('Content-Type'),
                $request->getContent(),
            );
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable) {
            throw ValidationException::withMessages([
                'payload' => [__('validation.invalid_payload')],
            ]);
        }

        try {
            $request->request->add($parsed['fields']);

            foreach ($parsed['files'] as $field => $file) {
                $request->files->set($field, $file);
            }

            return $next($request);
        } finally {
            foreach ($parsed['tempPaths'] as $tempPath) {
                if (is_file($tempPath)) {
                    @unlink($tempPath);
                }
            }
        }
    }
}
