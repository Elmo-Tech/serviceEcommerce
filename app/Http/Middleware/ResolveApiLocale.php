<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolveApiLocale
{
    /**
     * @var list<string>
     */
    private const SUPPORTED_LOCALES = ['ar', 'en'];

    public function handle(Request $request, Closure $next): Response
    {
        $locale = $this->resolveLocale($request->header('Accept-Language'));

        app()->setLocale($locale);
        $request->attributes->set('resolvedLocale', $locale);

        return $next($request);
    }

    private function resolveLocale(?string $acceptLanguage): string
    {
        if (! is_string($acceptLanguage) || $acceptLanguage === '') {
            return (string) config('app.locale', 'ar');
        }

        foreach (explode(',', $acceptLanguage) as $candidate) {
            $language = strtolower(trim(explode(';', $candidate)[0] ?? ''));
            $normalized = explode('-', $language)[0];

            if (in_array($normalized, self::SUPPORTED_LOCALES, true)) {
                return $normalized;
            }
        }

        return (string) config('app.locale', 'ar');
    }
}
