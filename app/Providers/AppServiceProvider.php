<?php

namespace App\Providers;

use App\Services\Orders\CustomerOrderResolver;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use LogicException;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureAdminCors();
        $this->validateAdminAuthenticationConfiguration();

        RateLimiter::for('admin-login', function (Request $request) {
            $email = $this->normalizedEmail((string) $request->input('email', ''));
            $ipAddress = (string) $request->ip();

            return Limit::perMinute(5)->by($email.'|'.$ipAddress);
        });

        RateLimiter::for('admin-refresh', function (Request $request) {
            $ipAddress = (string) $request->ip();
            $refreshToken = $request->input('refreshToken');
            $limiterKey = is_string($refreshToken) && $refreshToken !== ''
                ? $ipAddress.'|'.hash('sha256', $refreshToken)
                : $ipAddress;

            return Limit::perMinute(10)->by($limiterKey);
        });

        RateLimiter::for('admin-change-password', function (Request $request) {
            $userId = $request->user()?->getAuthIdentifier() ?? 'guest';

            return Limit::perMinute(5)->by($userId.'|'.(string) $request->ip());
        });

        RateLimiter::for('admin-forgot-password', function (Request $request) {
            return Limit::perMinute(5)->by(
                $this->normalizedEmail((string) $request->input('email', '')).'|'.(string) $request->ip(),
            );
        });

        RateLimiter::for('admin-reset-password', function (Request $request) {
            return Limit::perMinute(5)->by(
                $this->normalizedEmail((string) $request->input('email', '')).'|'.(string) $request->ip(),
            );
        });

        RateLimiter::for('public-orders-create', function (Request $request) {
            $limiterPhone = 'missing-phone';
            $customer = $request->input('customer');

            if (is_array($customer)) {
                $normalizedPhone = app(CustomerOrderResolver::class)
                    ->normalizeEgyptianPhone((string) ($customer['phone'] ?? ''));

                if (is_string($normalizedPhone) && $normalizedPhone !== '') {
                    $limiterPhone = $normalizedPhone;
                }
            }

            return Limit::perMinute(5)->by(((string) $request->ip()).'|'.$limiterPhone);
        });
    }

    private function configureAdminCors(): void
    {
        $origin = config('services.admin_frontend.origin');

        config()->set('cors.allowed_origins', is_string($origin) && $origin !== '' ? [$origin] : []);
    }

    private function validateAdminAuthenticationConfiguration(): void
    {
        $origin = config('services.admin_frontend.origin');

        if (! is_string($origin) || ! $this->isValidAdminFrontendOrigin($origin)) {
            throw new LogicException('ADMIN_FRONTEND_ORIGIN must be one exact origin without credentials, path, query, fragment, or wildcard.');
        }

        if (app()->isProduction()) {
            $requiredConfigurationFlags = [
                config('services.admin_frontend.origin_configured'),
            ];

            if (in_array(false, $requiredConfigurationFlags, true)) {
                throw new LogicException('Production Admin origin must be explicitly configured.');
            }

            if (parse_url($origin, PHP_URL_SCHEME) !== 'https') {
                throw new LogicException('ADMIN_FRONTEND_ORIGIN must use HTTPS in production.');
            }

            if (parse_url((string) config('app.url'), PHP_URL_SCHEME) !== 'https') {
                throw new LogicException('APP_URL must use HTTPS in production.');
            }
        }
    }

    private function isValidAdminFrontendOrigin(string $origin): bool
    {
        if ($origin === '' || $origin === '*' || filter_var($origin, FILTER_VALIDATE_URL) === false) {
            return false;
        }

        $parts = parse_url($origin);

        return is_array($parts)
            && isset($parts['scheme'], $parts['host'])
            && in_array($parts['scheme'], ['http', 'https'], true)
            && ! isset($parts['user'], $parts['pass'], $parts['query'], $parts['fragment'])
            && (! isset($parts['path']) || $parts['path'] === '');
    }

    private function normalizedEmail(string $email): string
    {
        return mb_strtolower(trim($email));
    }
}
