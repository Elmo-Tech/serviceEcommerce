<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ApplyAuthenticationResponseHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('Content-Language', app()->getLocale());
        $response->headers->set('Cache-Control', 'no-store, private');
        $response->headers->set('Pragma', 'no-cache');
        $response->headers->set('Vary', $this->appendVaryHeader(
            $response->headers->get('Vary'),
            'Accept-Language',
        ));

        return $response;
    }

    private function appendVaryHeader(?string $existingValue, string $newValue): string
    {
        $values = array_filter(array_map('trim', explode(',', (string) $existingValue)));

        if (! in_array($newValue, $values, true)) {
            $values[] = $newValue;
        }

        return implode(', ', $values);
    }
}
