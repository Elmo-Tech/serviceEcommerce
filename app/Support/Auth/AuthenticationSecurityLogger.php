<?php

declare(strict_types=1);

namespace App\Support\Auth;

use App\Models\User;
use Illuminate\Support\Facades\Log;
use Throwable;

class AuthenticationSecurityLogger
{
    /**
     * @var list<string>
     */
    private const SENSITIVE_KEY_FRAGMENTS = [
        'password',
        'token',
        'code',
        'authorization',
        'cookie',
        'credential',
        'error',
        'message',
        'sql',
        'stack',
        'trace',
        'avatarpath',
        'avatar_path',
        'hash',
    ];

    /**
     * @param  array<string, mixed>  $context
     */
    public function info(string $event, array $context = []): void
    {
        Log::info($event, $this->sanitize($context));
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function warning(string $event, array $context = []): void
    {
        Log::warning($event, $this->sanitize($context));
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function error(string $event, array $context = []): void
    {
        Log::error($event, $this->sanitize($context));
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    private function sanitize(array $context): array
    {
        $allowed = [];

        foreach ($context as $key => $value) {
            if ($this->isSensitiveKey($key)) {
                continue;
            }

            if ($key === 'email' && is_string($value)) {
                $allowed['email_hash'] = hash('sha256', mb_strtolower(trim($value)));

                continue;
            }

            if ($value instanceof User) {
                $allowed[$key.'_id'] = $value->getKey();

                continue;
            }

            if ($value instanceof Throwable) {
                $allowed[$key.'_class'] = $value::class;

                continue;
            }

            $allowed[$key] = $value;
        }

        return $allowed;
    }

    private function isSensitiveKey(string $key): bool
    {
        $normalized = strtolower(str_replace(['-', '_'], '', $key));

        foreach (self::SENSITIVE_KEY_FRAGMENTS as $fragment) {
            if (str_contains($normalized, $fragment)) {
                return true;
            }
        }

        return false;
    }
}
