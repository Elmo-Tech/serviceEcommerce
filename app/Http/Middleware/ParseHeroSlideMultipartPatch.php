<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\Http\StrictMultipartPatchParser;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

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
            $this->parser->parse((string) $request->header('Content-Type'), str_repeat('x', StrictMultipartPatchParser::MAX_FILE_BYTES + StrictMultipartPatchParser::MAX_OVERHEAD_BYTES + 1));
        }

        $parsed = $this->parser->parse(
            (string) $request->header('Content-Type'),
            $request->getContent(),
        );

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
