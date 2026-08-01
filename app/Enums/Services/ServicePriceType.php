<?php

declare(strict_types=1);

namespace App\Enums\Services;

enum ServicePriceType: int
{
    case FIXED = 0;
    case START_FROM = 1;
}
