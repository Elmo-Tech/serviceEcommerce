<?php

declare(strict_types=1);

namespace App\Enums;

enum RefreshTokenRevocationReason: string
{
    case LOGIN_REPLACED = 'login_replaced';
    case REFRESHED = 'refreshed';
    case LOGOUT = 'logout';
    case PASSWORD_CHANGED = 'password_changed';
    case PASSWORD_RESET = 'password_reset';
    case USER_INACTIVE = 'user_inactive';
    case REFRESH_REUSE_DETECTED = 'refresh_reuse_detected';
}
