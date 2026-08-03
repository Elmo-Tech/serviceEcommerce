<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\Http\StrictMultipartPatchParser;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class ParseHeroSlideMultipartPatch
{
    public function __construct(private readonly StrictMultipartPatchParser $parser) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->isMethod('PATCH') || ! str_starts_with(strtolower((string) $request->header('Content-Type')), 'multipart/form-data')) {
            return $next($request);
        }

        $contentLength = (int) $request->server('CONTENT_LENGTH', 0);

        if ($contentLength > StrictMultipartPatchParser::MAX_FILE_BYTES + StrictMultipartPatchParser::MAX_OVERHEAD_BYTES) {
            throw ValidationException::withMessages([
                'payload' => [__('validation.invalid_payload')],
            ]);
        }

        try {
            $content = $request->getContent();
        } catch (Throwable) {
            throw ValidationException::withMessages([
                'payload' => [__('validation.invalid_payload')],
            ]);
        }

        $parsed = $this->parser->parse((string) $request->header('Content-Type'), $content);

        try {
            $request->request->add($parsed['fields']);

            if ($parsed['file'] !== null) {
                $request->files->set('image', $parsed['file']);
            }

            return $next($request);
        } finally {
            if (is_string($parsed['tempPath']) && is_file($parsed['tempPath'])) {
                @unlink($parsed['tempPath']);
            }
        }
    }
}
